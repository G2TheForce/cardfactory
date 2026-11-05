# Running SmartCard Studio locally on Windows

A step-by-step PowerShell setup using SQLite, so there is no database server to install.
For a production server see [DEPLOYMENT.md](DEPLOYMENT.md).

## 1. Check the tools

```powershell
php -v; composer -V; node -v; npm -v; git --version
php -r "echo PHP_INT_SIZE, PHP_EOL;"
php -m | Select-String "sqlite|mbstring|gd|intl|zip|bcmath|fileinfo|openssl"
```

You need PHP 8.3+, Composer 2, Node.js 20+ and Git, plus the extensions `pdo_sqlite`,
`mbstring`, `gd`, `intl`, `zip`, `bcmath`, `fileinfo` and `openssl` (enable them in `php.ini`).

**PHP must be the 64-bit (x64) build.** `PHP_INT_SIZE` must print `8`. With the 32-bit (x86)
build `composer install` fails with `maennchen/zipstream-php ... requires php-64bit`.

### Switching from 32-bit to 64-bit PHP

Download the **VS17 x64 Non Thread Safe** zip from <https://windows.php.net/download/> (or
<https://www.php.net/downloads.php?os=windows>), then swap it in, keeping your `php.ini`.
Adjust `C:\php` to the folder your PHP lives in (`(Get-Command php).Source` shows it):

```powershell
Rename-Item C:\php C:\php-x86-old
Expand-Archive "$HOME\Downloads\php-8.5.7-nts-Win32-vs17-x64.zip" -DestinationPath C:\php
Copy-Item C:\php-x86-old\php.ini C:\php\
php -r "echo PHP_INT_SIZE, PHP_EOL;"   # 8
```

If PHP reports a missing `VCRUNTIME140.dll`, install the
[Visual C++ Redistributable x64](https://aka.ms/vs/17/release/vc_redist.x64.exe).

## 2. Get the code and packages

```powershell
cd $HOME
git clone https://github.com/G2TheForce/cardfactory.git smartcard-studio
cd smartcard-studio
composer install
npm install
```

## 3. Settings, database, demo data and assets

```powershell
(Get-Content .env.example) | Where-Object { $_ -notmatch '^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)=' } | ForEach-Object { $_ -replace '^DB_CONNECTION=mysql', 'DB_CONNECTION=sqlite' } | Set-Content .env
New-Item database\database.sqlite -ItemType File -Force | Out-Null
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run build
```

With `DB_DATABASE` removed, Laravel uses `database\database.sqlite`.

## 4. Chrome for PNG and PDF exports

Chrome and Edge are found automatically in their usual install folders. To use another
browser binary, set `CHROME_PATH` in `.env` with **single quotes**. Double quotes make the
`.env` parser treat the backslashes as escape sequences ("Failed to parse dotenv file"):

```ini
CHROME_PATH='C:\Program Files\Google\Chrome\Application\chrome.exe'
```

For CMYK PDFs install [Ghostscript](https://ghostscript.com/releases/gsdnld.html); `gswin64c`
is found if it is on the PATH, otherwise set
`GHOSTSCRIPT_PATH='C:\Program Files\gs\gs<version>\bin\gswin64c.exe'`.

Without Chrome the app still works; only PNG and PDF exports are disabled.

## 5. Run it

Window 1:

```powershell
php artisan serve
```

Window 2 (builds exports and bulk runs, sends mail):

```powershell
cd $HOME\smartcard-studio
php artisan queue:work --queue=exports,default
```

Open <http://localhost:8000>. Demo accounts (local only) all use the password `password`:
`superadmin@`, `admin@`, `designer@`, `sales@` and `customer@smartcard.test`.

## Updating later

```powershell
git pull
composer install
npm install
npm run build
php artisan migrate
```

Restart the queue worker afterwards (Ctrl+C, then start it again).

## Troubleshooting

| Problem | Fix |
| ------- | --- |
| `requires php-64bit` during `composer install` | Install the x64 PHP build (step 1) |
| `The environment file is invalid` | A value with backslashes is in double quotes; use single quotes |
| Exports stay "queued" | The queue worker (window 2) is not running |
| PNG/PDF export options disabled | Chrome not found; set `CHROME_PATH` (step 4) and restart both windows |
| Styles missing / Vite manifest error | Run `npm run build` |
| `could not find driver` | Enable `extension=pdo_sqlite` in `php.ini` |
