<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Klsheng\Myinvois\Example\Ubl\CreateDocumentExample;
use Klsheng\Myinvois\Helper\MyInvoisHelper;
use Klsheng\Myinvois\MyInvoisClient;
use Klsheng\Myinvois\Ubl\Constant\InvoiceTypeCodes;

class VersionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:invoice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display the application version';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $prodMode = false;
        $client = new MyInvoisClient('836f102a-1d13-44da-a110-d528596d2533', '644f79ce-9b2e-4260-bd5e-4f36b7da69db', $prodMode);

        $client->login();
        // $access_token = $client->getAccessToken();
        // $client->setOnbehalfof('IG505987930a70');
        // $this->info("Access Token: {$access_token}");
        
        $getTin = $client->searchTaxPayerTin('', 'NRIC', '010801101477');
        $this->info("Response Details:");
        $this->line(print_r($getTin, true));
        $this->info("TIN: " . $getTin['tin']);
        
        $id = 'INV20240418105410';
        $supplier = [
            'TIN' => 'IG50598793070',
            'BRN' => '010801101477',
        ];
        $customer = [
            'TIN' => 'IG50598793070',
            'BRN' => '010801101477',
        ];
        $delivery = [
            'TIN' => 'IG50598793070',
            'BRN' => '010801101477',
        ];
        
        $certPath = base_path('org.crt');
        $keyPath = base_path('org.key');
        
        $example = new CreateDocumentExample();
        $invoice = $example->createXmlDocument(
            InvoiceTypeCodes::INVOICE,
            $id,
            $supplier,
            $customer,
            $delivery,
             false,
            $certPath,
            $keyPath
        );

        // $this->line(print_r($invoice, true));
        
        $documents = [];
        $document = MyInvoisHelper::getSubmitDocument($id, $invoice);
        $documents[] = $document;
        
        $response = $client->submitDocument($documents);
        $this->info("Submit Response:");
        $this->line(print_r($response, true));
        
        return Command::SUCCESS;
    }
}
