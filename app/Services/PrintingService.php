<?php

namespace App\Services;

use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use Exception;
use WindowsPrintConnectorTest;

class PrintingService
{
    public function printDemoReceipt()
    {
        $connector = new WindowsPrintConnector("XP-58");
        $printer = null;
        try {
            $printer = new Printer($connector);
            $printer->initialize();

            // Header
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text("SUPERMARKET\n");
            $printer->setEmphasis(false);
            $printer->text("Lorem ipsum 258\n");
            $printer->text("City Index - 02025\n");
            $printer->text("Tel.: +456-468-987-02\n");
            $printer->feed();
            $printer->text(str_repeat('-', 32) . "\n");
            $printer->feed();

            // Cashier/Manager info
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Cashier:");
            $printer->setJustification(Printer::JUSTIFY_RIGHT);
            $printer->text("#3\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Manager:");
            $printer->setJustification(Printer::JUSTIFY_RIGHT);
            $printer->text("Eric Steer\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->feed();
            $printer->text(str_repeat('-', 32) . "\n");
            $printer->feed();

            // Table header (Font B, aligned, one line)
            $printer->setFont(Printer::FONT_B);
            $printer->setEmphasis(true);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text(sprintf("%-28s %3s %7s\n", 'Item', 'Qty', 'Price'));
            $printer->setEmphasis(false);
            $printer->feed();

            // Product list in smaller font, with wrapping
            $items = [
                ['name' => 'lewis', 'qty' => 1, 'price' => 9.20],
                ['name' => 'hamilton', 'qty' => 1, 'price' => 19.20],
                ['name' => 'Lorem ipsum dolor sit', 'qty' => 1, 'price' => 15.00],
                ['name' => 'Lorem ipsum dolor sit amet consectetur', 'qty' => 1, 'price' => 19.20],
            ];
            $subtotal = 0;
            foreach ($items as $item) {
                $nameLines = explode("\n", wordwrap($item['name'], 28, "\n", true));
                // Print first line with qty and price
                $printer->text(sprintf("%-28s %3d %7s\n", $nameLines[0], $item['qty'], '$'.number_format($item['price'],2)));
                // Print any additional lines of the name, leaving qty and price blank
                for ($i = 1; $i < count($nameLines); $i++) {
                    $printer->text(sprintf("%-28s %3s %7s\n", $nameLines[$i], '', ''));
                }
                $subtotal += $item['price'] * $item['qty'];
            }
            $printer->setFont(); // Reset to default font
            $printer->feed();
            $printer->text(str_repeat('-', 32) . "\n");
            $printer->feed();

            // Subtotal, cash, change
            $printer->setEmphasis(true);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text(sprintf("%-18s %13s\n", 'Sub Total', '$'.number_format($subtotal,2)));
            $printer->setEmphasis(false);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text(sprintf("%-18s %13s\n", 'CASH', '$200.00'));
            $printer->text(sprintf("%-18s %13s\n", 'CHANGE', '$'.number_format(200-$subtotal,2)));
            $printer->feed();
            $printer->text(str_repeat('-', 32) . "\n");
            $printer->feed();

            // Barcode
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->barcode('123456789012', Printer::BARCODE_JAN13);
            $printer->feed();

            // Thank you message
            $printer->setEmphasis(true);
            $printer->text("THANK YOU!\n");
            $printer->setEmphasis(false);
            $printer->text("Glad to see you again!\n");
            $printer->feed();

            $printer->cut();
            return true;
        } catch (Exception $e) {
            throw $e;
        } finally {
            if ($printer instanceof Printer) {
                $printer->close();
            }
        }
    }

    public function printText($text)
    {
        $printer = null;
        try {
            $connector = new \Mike42\Escpos\PrintConnectors\CupsPrintConnector("Printer_USB_Printer_Port");
            $printer = new \Mike42\Escpos\Printer($connector);
            $printer->initialize();
            $printer->text($text . "\n");
            $printer->cut();
            return true;
        } catch (\Exception $e) {
            throw $e;
        } finally {
            if ($printer instanceof \Mike42\Escpos\Printer) {
                $printer->close();
            }
        }
    }

    /**
     * Print a receipt by passing the order array to an external print.php script via a temp JSON file.
     *
     * @param array $order The order array matching the specified JSON format.
     * @param string $printScriptPath Absolute path to the print.php script
     * @return string Output from the print.php script
     * @throws Exception
     */
    public function printReceipt(array $order, string $printScriptPath)
    {
        // Prepare the command
        $cmd = escapeshellcmd("php " . $printScriptPath);

        // Open process with pipes
        $descriptorspec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];
        $process = proc_open($cmd, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            throw new \Exception("Could not start print script process");
        }

        // Write JSON to stdin
        fwrite($pipes[0], json_encode($order));
        fclose($pipes[0]);

        // Read output and error
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $resultCode = proc_close($process);

        if ($resultCode !== 0) {
            throw new \Exception("Printing failed: " . $error);
        }
        return $output;
    }
} 