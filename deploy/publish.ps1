param(
    [Parameter(Mandatory = $true)] [string] $KeyPath,
    [Parameter(Mandatory = $true)] [string] $KnownHostsPath,
    [string] $HostName = '184.198.12.185'
)

$ErrorActionPreference = 'Stop'
$repo = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$bundle = Join-Path ([IO.Path]::GetTempPath()) ('the-arrow-church-' + [guid]::NewGuid().ToString('N') + '.bundle')
$remoteBundle = '/home/admin/repos/the-arrow-church-deploy.bundle'

try {
    Push-Location $repo
    try {
        & git bundle create $bundle main
        if ($LASTEXITCODE -ne 0) { throw 'Could not create Git bundle.' }
    } finally { Pop-Location }

    & scp -i $KeyPath -o IdentitiesOnly=yes -o BatchMode=yes -o "UserKnownHostsFile=$KnownHostsPath" $bundle "admin@${HostName}:$remoteBundle"
    if ($LASTEXITCODE -ne 0) { throw 'Could not copy Git bundle.' }

    $remoteCommand = "set -e; git --git-dir=/home/admin/repos/the-arrow-church.git fetch $remoteBundle main:refs/heads/main; /home/admin/repos/the-arrow-church.git/hooks/post-receive main; rm -f $remoteBundle"
    & ssh -i $KeyPath -o IdentitiesOnly=yes -o BatchMode=yes -o "UserKnownHostsFile=$KnownHostsPath" "admin@$HostName" $remoteCommand
    if ($LASTEXITCODE -ne 0) { throw 'Lightsail deployment failed.' }
} finally {
    Remove-Item -LiteralPath $bundle -ErrorAction SilentlyContinue
}
