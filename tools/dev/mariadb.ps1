# Starts, stops or reports the portable MariaDB used for local development and tests.
# Override the install folder with the CMS_MARIADB_HOME environment variable.
param([ValidateSet('start', 'stop', 'status')][string]$Action = 'status')

$mdb  = if ($env:CMS_MARIADB_HOME) { $env:CMS_MARIADB_HOME } else { 'C:\tools\mariadb-11.4.13-winx64' }
$port = 3307

function Test-Up {
    try { $c = [Net.Sockets.TcpClient]::new(); $c.Connect('127.0.0.1', $port); $c.Close(); return $true } catch { return $false }
}

switch ($Action) {
    'start' {
        if (Test-Up) { "MariaDB already listening on 127.0.0.1:$port"; break }
        Start-Process -FilePath "$mdb\bin\mariadbd.exe" -ArgumentList "--defaults-file=`"$mdb\data\my.ini`"", '--bind-address=127.0.0.1' -WindowStyle Hidden
        for ($i = 0; $i -lt 40 -and -not (Test-Up); $i++) { Start-Sleep -Milliseconds 250 }
        if (Test-Up) { "MariaDB started on 127.0.0.1:$port" } else { throw 'MariaDB did not start; check the .err file in the data folder.' }
    }
    'stop' {
        $pw = Get-Content "$mdb\ROOT_PASSWORD.txt"
        & "$mdb\bin\mariadb-admin.exe" --host=127.0.0.1 --port=$port --user=root "--password=$pw" shutdown
        'MariaDB stopped'
    }
    'status' { if (Test-Up) { "MariaDB listening on 127.0.0.1:$port" } else { 'MariaDB is not running' } }
}
