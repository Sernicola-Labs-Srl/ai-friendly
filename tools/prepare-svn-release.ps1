[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string] $SvnCheckoutPath,

    [ValidatePattern('^[0-9]+(\.[0-9]+)+$')]
    [string] $Version = '2.1.1',

    [switch] $Commit
)

$ErrorActionPreference = 'Stop'

function Invoke-Svn {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]] $Arguments)

    & $script:svnCommand @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "SVN command failed: svn $($Arguments -join ' ')"
    }
}

$sourceRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$checkoutRoot = (Resolve-Path $SvnCheckoutPath).Path
$trunk = Join-Path $checkoutRoot 'trunk'
$tagPath = Join-Path (Join-Path $checkoutRoot 'tags') $Version
$mainFile = Join-Path $sourceRoot 'sernicola-labs-ai-friendly.php'
$readmeFile = Join-Path $sourceRoot 'readme.txt'

$svnCandidate = Get-Command svn -ErrorAction SilentlyContinue
if ($svnCandidate) {
    $script:svnCommand = $svnCandidate.Source
}
elseif (Test-Path 'C:\Program Files\SlikSvn\bin\svn.exe') {
    $script:svnCommand = 'C:\Program Files\SlikSvn\bin\svn.exe'
}
else {
    throw 'Subversion (svn) is not available. Install an SVN client, then retry.'
}
if (-not (Test-Path (Join-Path $checkoutRoot '.svn'))) {
    throw "'$checkoutRoot' is not an SVN checkout. Run svn checkout first."
}
if (Test-Path $tagPath) {
    throw "The SVN tag '$Version' already exists. Choose a new version; tags are immutable releases."
}
if (-not (Test-Path $mainFile) -or -not (Test-Path $readmeFile)) {
    throw 'Expected plugin main file or readme.txt is missing from the source repository.'
}

$headerVersion = (Select-String -Path $mainFile -Pattern '^\s*\*\s*Version:\s*(\S+)').Matches[0].Groups[1].Value
$stableTag = (Select-String -Path $readmeFile -Pattern '^Stable tag:\s*(\S+)' -CaseSensitive:$false).Matches[0].Groups[1].Value
if ($headerVersion -ne $Version -or $stableTag -ne $Version) {
    throw "Version mismatch: plugin header is '$headerVersion', Stable tag is '$stableTag', requested release is '$Version'."
}

$existingTrunkFiles = Get-ChildItem -LiteralPath $trunk -Force -Recurse -File
if ($existingTrunkFiles.Count -gt 0) {
    throw 'trunk is not empty. This script intentionally supports only the first SVN release to avoid replacing an existing release accidentally.'
}

$releaseItems = @(
    'sernicola-labs-ai-friendly.php',
    'uninstall.php',
    'readme.txt',
    'README.md',
    'CHANGELOG.md',
    'LICENSE',
    'admin',
    'includes'
)

foreach ($item in $releaseItems) {
    Copy-Item -LiteralPath (Join-Path $sourceRoot $item) -Destination $trunk -Recurse -Force
}

Push-Location $checkoutRoot
try {
    Invoke-Svn add --force trunk

    Get-ChildItem -LiteralPath $trunk -Recurse -File -Filter '*.php' | ForEach-Object {
        $relativePath = $_.FullName.Substring( $checkoutRoot.Length + 1 )
        Invoke-Svn propset svn:mime-type text/plain $relativePath
    }

    Invoke-Svn copy trunk (Join-Path 'tags' $Version)
    Invoke-Svn status
    Invoke-Svn diff --summarize

    if ($Commit) {
        Invoke-Svn commit -m "Release $Version"
    }
    else {
        Write-Host "Local SVN release '$Version' is prepared. Review the output, then rerun with -Commit to publish."
    }
}
finally {
    Pop-Location
}
