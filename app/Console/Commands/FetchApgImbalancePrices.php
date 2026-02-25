<?php

namespace App\Console\Commands;

use App\Models\ImbalancePrice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchApgImbalancePrices extends Command
{
    protected $signature = 'fetch:apg-imbalance-prices {--days= : Počet dní do minulosti, pokud je tabulka prázdná}';

    protected $description = 'Fetch imbalance prices from APG API and upsert them into database';

    protected string $baseUrl;
    protected string $eic = '10YAT-APG------L';

    public function __construct()
    {
        parent::__construct();
        // Načtení URL z configu
        $this->baseUrl = config('services.apg.url');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');

        // Aktuální čas v Praze
        $now = Carbon::now('Europe/Prague');

        // Chceme stahovat "až po dnešní den včetně", iterujeme do půlnoci zítřka
        $endOfPeriod = $now->copy()->addDay()->startOfDay();

        if ($days !== null) {
            // S parametrem --days
            $startOfPeriod = $now->copy()->startOfDay()->subDays((int)$days);
        } else {
            // Bez parametru --days: hledáme poslední záznam konkrétního EIC
            $lastRecord = ImbalancePrice::where('eic', $this->eic)
                ->orderBy('time', 'desc')
                ->first();

            if ($lastRecord) {
                // Den předcházející dni posledního záznamu (od půlnoci)
                $startOfPeriod = Carbon::parse($lastRecord->time, 'Europe/Prague')
                    ->subDay()
                    ->startOfDay();
            } else {
                // Pojistka pro případ, že se zavolá bez parametru, ale tabulka je úplně prázdná
                Log::warning('APG [Imbalance Prices] Tabulka je pro EIC prázdná, nebyl zadán parametr --days. Použito výchozích 7 dní.');
                $startOfPeriod = $now->copy()->startOfDay()->subDays(7);
            }
        }

        Log::info("APG [Imbalance Prices] Začínám stahovat data od {$startOfPeriod->toDateString()} do {$now->toDateString()}");

        // Iterujeme po jednotlivých dnech (od půlnoci do půlnoci)
        $currentStart = $startOfPeriod->copy();
        while ($currentStart < $endOfPeriod) {
            $currentEnd = $currentStart->copy()->addDay();

            $this->fetchAndSaveForPeriod($currentStart, $currentEnd);

            $currentStart = $currentEnd;
        }

        Log::info('APG [Imbalance Prices] Stahování úspěšně dokončeno.');
        $this->info('Kompletně hotovo!');
    }

    /**
     * Stáhne a uloží data pro konkrétní 24h interval.
     */
    protected function fetchAndSaveForPeriod(Carbon $from, Carbon $to): void
    {
        $fromLocal = $from->format('Y-m-d\THis');
        $toLocal = $to->format('Y-m-d\THis');

        // Sestavení endpointu
        $url = "{$this->baseUrl}/AE/Data/English/PT15M/{$fromLocal}/{$toLocal}";

        Log::info("APG [Imbalance Prices] Volám API: {$url}");

        $response = Http::get($url, [
            'p_aeTimeSeriesQuality' => 'FirstValue'
        ]);

        if ($response->failed()) {
            Log::error("APG [Imbalance Prices] API vrátilo chybu pro interval {$fromLocal} - {$toLocal}: " . $response->body());
            $this->error("Chyba API pro interval {$fromLocal} - {$toLocal}");
            return;
        }

        $data = $response->json();

        // Zanoření podle reálného výstupu APG
        $rows = $data['ResponseData']['ValueRows'] ?? [];

        if (empty($rows)) {
            Log::info("APG [Imbalance Prices] Žádná data pro interval {$fromLocal} - {$toLocal}");
            return;
        }

        $upsertData = [];

        foreach ($rows as $row) {
            if (!isset($row['DF']) || !isset($row['TF']) || !isset($row['V'][0]['V'])) {
                continue;
            }

            // Spojení DF (Date From) a TF (Time From)
            $timeString = $row['DF'] . ' ' . $row['TF'];

            // Reálný formát APG je d.m.Y (např. 21.02.2026 00:00)
            $time = Carbon::createFromFormat('d.m.Y H:i', $timeString, 'Europe/Prague')->toDateTimeString();

            // Získání hodnoty
            $price = $row['V'][0]['V'];

            $upsertData[] = [
                'time' => $time,
                'eic' => $this->eic,
                'price_up' => $price,
                'price_down' => $price, // Pro AT je shodná s price_up
                'currency' => 'EUR',
                'unit' => 'MWH',
                'source' => 'apg',
            ];
        }

        if (!empty($upsertData)) {
            // Upsert do databáze (záznamy nahrazujeme / přidáváme)
            ImbalancePrice::upsert(
                $upsertData,
                ['time', 'eic', 'currency', 'source'],
                ['price_up', 'price_down']
            );

            $count = count($upsertData);
            Log::info("APG [Imbalance Prices] Uloženo / aktualizováno {$count} záznamů pro interval {$fromLocal} - {$toLocal}");
        }
    }
}
