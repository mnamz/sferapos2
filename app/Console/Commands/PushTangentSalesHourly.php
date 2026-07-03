<?php

namespace App\Console\Commands;

use App\Models\TangentSalesHourly;
use App\Services\Tangent\HourlySalesAggregator;
use App\Services\Tangent\TangentClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PushTangentSalesHourly extends Command
{
    protected $signature = 'tangent:push-sales
        {--date= : Only process this KL date (YYYY-MM-DD)}
        {--dry-run : Compute and print payloads without sending or writing}
        {--test-connection : Fetch a token and report, without sending sales}
        {--force : Re-send every day in the window regardless of stored status}';

    protected $description = 'Aggregate hourly sales and push them to the Tangent SalesHourly API';

    public function handle(HourlySalesAggregator $aggregator, TangentClient $client): int
    {
        $tz = config('services.tangent.timezone', 'Asia/Kuala_Lumpur');

        if ($this->option('test-connection')) {
            if (! $client->isConfigured()) {
                return $this->bailNotConfigured();
            }

            return $this->testConnection($client);
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            if (! $client->isConfigured()) {
                return $this->bailNotConfigured();
            }
        } elseif (! $client->isEnabled()) {
            $this->warn('Tangent integration is disabled or not configured. Set TANGENT_ENABLED=true to send.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $sent = $skipped = $failed = 0;

        foreach ($this->resolveDays($tz) as $day) {
            $records = $aggregator->aggregate($day);

            if ($dryRun) {
                $this->line("== {$day->format('Y-m-d')} ==");
                $this->line(json_encode(
                    ['sales' => array_map(fn ($r) => ['sale' => $this->previewRecord($day, $r)], $records)],
                    JSON_PRETTY_PRINT
                ));

                continue;
            }

            $dateKey = $day->format('Y-m-d');
            $dayNeedsSend = $force;

            foreach ($records as $hour => $agg) {
                $hash = sha1(json_encode($agg));
                $row = TangentSalesHourly::firstOrNew(['sale_date' => $dateKey, 'hour' => $hour]);
                $changed = ! $row->exists || $row->payload_hash !== $hash;

                $row->fill($agg);
                $row->sale_date = $dateKey;
                $row->hour = $hour;
                $row->payload_hash = $hash;
                if ($changed) {
                    $row->status = 'pending';
                }
                $row->save();

                if ($row->status !== 'sent') {
                    $dayNeedsSend = true;
                }
            }

            if (! $dayNeedsSend) {
                $skipped++;

                continue;
            }

            $rows = TangentSalesHourly::where('sale_date', $dateKey)->orderBy('hour')->get();
            $result = $client->sendSales($rows->map->toApiRecord()->all());

            $update = [
                'response_status' => $result['status'],
                'response_body' => Str::limit((string) $result['body'], 1000),
            ];

            if ($result['ok']) {
                $update['status'] = 'sent';
                $update['synced_at'] = now();
                $sent++;
                $this->info("Sent {$dateKey} (24 records).");
            } else {
                $update['status'] = 'failed';
                $failed++;
                $this->error("Failed {$dateKey}: {$result['body']}");
            }

            TangentSalesHourly::where('sale_date', $dateKey)->update($update);
        }

        if (! $dryRun) {
            $this->info("Done. Sent: {$sent}, skipped: {$skipped}, failed: {$failed}.");
        }

        return self::SUCCESS;
    }

    /** @return array<int, CarbonImmutable> */
    private function resolveDays(string $tz): array
    {
        if ($date = $this->option('date')) {
            return [CarbonImmutable::parse($date, $tz)->startOfDay()];
        }

        $today = CarbonImmutable::now($tz)->startOfDay();
        $lookback = max(1, (int) config('services.tangent.lookback_days', 7));

        $days = [];
        for ($i = $lookback - 1; $i >= 0; $i--) {
            $days[] = $today->subDays($i);
        }

        return $days;
    }

    private function testConnection(TangentClient $client): int
    {
        if ($token = $client->token()) {
            $this->info('Tangent token obtained successfully (length '.strlen($token).').');

            return self::SUCCESS;
        }

        $this->error('Failed to obtain a Tangent token. Check credentials and logs.');

        return self::FAILURE;
    }

    /** @param array<string, mixed> $agg */
    private function previewRecord(CarbonImmutable $day, array $agg): array
    {
        return (new TangentSalesHourly(array_merge($agg, ['sale_date' => $day->format('Y-m-d')])))
            ->toApiRecord();
    }

    private function bailNotConfigured(): int
    {
        $this->error('Tangent is not configured (need base_url, username, password, machine_id).');

        return self::FAILURE;
    }
}
