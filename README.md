# APG Imbalance Prices Fetcher

Tento projekt je backendová aplikace v Laravelu, která slouží ke stahování a ukládání cen odchylek (Imbalance Prices) z veřejného API rakouského provozovatele přenosové soustavy (APG - Austrian Power Grid).

Aplikace je plně kontejnerizována pomocí **Laravel Sail** (Docker), což zajišťuje snadné a konzistentní spuštění na jakémkoliv vývojovém stroji.

## 🛠 Požadavky na systém

Pro bezproblémové spuštění projektu je potřeba mít nainstalováno:
* [Docker](https://www.docker.com/) a běžící Docker Engine / Docker Desktop.
* (Volitelně) `php` a `composer` lokálně, pokud nechcete instalovat závislosti přes Docker kontejner.

---

## 🚀 Instalace a spuštění

**1. Klonování repozitáře a příprava prostředí**
Naklonujte si repozitář a zkopírujte konfigurační soubor:
```bash
git clone <url-vaseho-repozitare>
cd ampower # (nebo název složky projektu)
cp .env.example .env
