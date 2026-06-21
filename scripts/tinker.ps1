param(
    [string] $Execute,
    [string] $EncodedExecute,
    [string] $File,

    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]] $TinkerArgs
)

$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$PsyshRoot = Join-Path $ProjectRoot '.codex-temp\psysh'

$AppData = Join-Path $PsyshRoot 'appdata'
$ConfigHome = Join-Path $PsyshRoot 'config'
$RuntimeDir = Join-Path $PsyshRoot 'runtime'

New-Item -ItemType Directory -Force -Path $AppData, $ConfigHome, $RuntimeDir | Out-Null

$env:APPDATA = $AppData
$env:XDG_CONFIG_HOME = $ConfigHome
$env:XDG_RUNTIME_DIR = $RuntimeDir

Push-Location $ProjectRoot

try {
    if ($File) {
        $ResolvedFile = Resolve-Path -LiteralPath $File
        $PhpFile = $ResolvedFile.Path.Replace('\', '/').Replace("'", "\\'")
        $TinkerArgs = @('--execute', "require '$PhpFile';")
    } elseif ($EncodedExecute) {
        $Execute = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String($EncodedExecute))
        $TinkerArgs = @('--execute', $Execute)
    } elseif ($Execute) {
        $TinkerArgs = @('--execute', $Execute)
    } elseif ($TinkerArgs.Count -gt 0 -and $TinkerArgs[0] -like '--execute=*' -and $TinkerArgs.Count -gt 1) {
        $TinkerArgs = @((($TinkerArgs -join ' ') -replace '^--execute=', '--execute='))
    }

    & php artisan tinker @TinkerArgs
    exit $LASTEXITCODE
} finally {
    Pop-Location
}
