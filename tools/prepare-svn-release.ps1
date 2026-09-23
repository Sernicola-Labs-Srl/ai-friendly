[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string] $SvnCheckoutPath,

    # Defaults to the version declared in the plugin header.
    [ValidatePattern('^$|^[0-9]+(\.[0-9]+)+$')]
    [string] $Version = '',

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

function Get-RelativePath {
    param([string] $Root, [string] $Path)
    return $Path.Substring($Root.TrimEnd('\').Length + 1)
}

function Get-TreeFingerprint {
    param([string] $Root)
    $lines = Get-ChildItem -LiteralPath $Root -Recurse -File -Force | Sort-Object FullName | ForEach-Object {
        (Get-RelativePath $Root $_.FullName) + ' ' + (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash
    }
    return ($lines -join "`n")
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

$sourceRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$checkoutRoot = (Resolve-Path $SvnCheckoutPath).Path
$trunk = Join-Path $checkoutRoot 'trunk'
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
if (-not (Test-Path $mainFile) -or -not (Test-Path $readmeFile)) {
    throw 'Expected plugin main file or readme.txt is missing from the source repository.'
}

$headerVersion = (Select-String -Path $mainFile -Pattern '^\s*\*\s*Version:\s*(\S+)').Matches[0].Groups[1].Value
$constantVersion = (Select-String -Path $mainFile -Pattern "define\(\s*'SAIFR_VERSION',\s*'([^']+)'").Matches[0].Groups[1].Value
$stableTag = (Select-String -Path $readmeFile -Pattern '^Stable tag:\s*(\S+)' -CaseSensitive:$false).Matches[0].Groups[1].Value
if ($Version -eq '') {
    $Version = $headerVersion
}
if ($headerVersion -ne $Version -or $stableTag -ne $Version -or $constantVersion -ne $Version) {
    throw "Version mismatch: header '$headerVersion', SAIFR_VERSION '$constantVersion', Stable tag '$stableTag', requested '$Version'."
}
if (-not (Select-String -Path $readmeFile -Pattern "^= $([regex]::Escape($Version)) =" -Quiet)) {
    throw "readme.txt has no changelog entry '= $Version ='."
}

# Only files tracked by git and without uncommitted changes are released.
$gitChanges = & git -C $sourceRoot status --porcelain -- @releaseItems
if ($LASTEXITCODE -ne 0) {
    throw 'Unable to read the git status of the source repository.'
}
if ($gitChanges) {
    throw "The release files have uncommitted changes. Commit them first:`n$($gitChanges -join "`n")"
}
$trackedFiles = & git -C $sourceRoot ls-files -- @releaseItems
if ($LASTEXITCODE -ne 0 -or -not $trackedFiles) {
    throw 'Unable to list the release files tracked by git.'
}
$sourceFiles = @{}
foreach ($file in $trackedFiles) {
    $sourceFiles[($file -replace '/', '\')] = Join-Path $sourceRoot ($file -replace '/', '\')
}

Push-Location $checkoutRoot
try {
    Invoke-Svn update --quiet
    $tagPath = Join-Path (Join-Path $checkoutRoot 'tags') $Version
    if (Test-Path $tagPath) {
        throw "The SVN tag '$Version' already exists. Choose a new version; tags are immutable releases."
    }
    $pending = & $script:svnCommand status --quiet
    if ($pending) {
        throw "The SVN checkout already has local changes. Review them or run 'svn revert -R .' first:`n$($pending -join "`n")"
    }
    if (-not (Test-Path $trunk)) {
        New-Item -ItemType Directory -Path $trunk | Out-Null
    }

    # Remove from trunk the files and directories that are no longer released.
    foreach ($file in @(Get-ChildItem -LiteralPath $trunk -Recurse -File -Force)) {
        $relative = Get-RelativePath $trunk $file.FullName
        if (-not $sourceFiles.ContainsKey($relative) -and (Test-Path -LiteralPath $file.FullName)) {
            Invoke-Svn delete --force --quiet $file.FullName
        }
    }
    $sourceDirectories = @{}
    foreach ($relative in $sourceFiles.Keys) {
        $parent = Split-Path $relative -Parent
        while ($parent) {
            $sourceDirectories[$parent] = $true
            $parent = Split-Path $parent -Parent
        }
    }
    $trunkDirectories = @(Get-ChildItem -LiteralPath $trunk -Recurse -Directory -Force | Sort-Object { $_.FullName.Length } -Descending)
    foreach ($directory in $trunkDirectories) {
        $relative = Get-RelativePath $trunk $directory.FullName
        if (-not $sourceDirectories.ContainsKey($relative) -and (Test-Path -LiteralPath $directory.FullName)) {
            Invoke-Svn delete --force --quiet $directory.FullName
        }
    }

    # Copy the released files over trunk and schedule the new ones.
    foreach ($relative in $sourceFiles.Keys) {
        $destination = Join-Path $trunk $relative
        $destinationDirectory = Split-Path $destination -Parent
        if (-not (Test-Path $destinationDirectory)) {
            New-Item -ItemType Directory -Path $destinationDirectory -Force | Out-Null
        }
        Copy-Item -LiteralPath $sourceFiles[$relative] -Destination $destination -Force
    }
    Invoke-Svn add --force --quiet trunk

    Get-ChildItem -LiteralPath $trunk -Recurse -File -Filter '*.php' | ForEach-Object {
        Invoke-Svn propset --quiet svn:mime-type text/plain (Get-RelativePath $checkoutRoot $_.FullName)
    }

    Invoke-Svn copy --quiet trunk (Join-Path 'tags' $Version)
    if ((Get-TreeFingerprint $trunk) -ne (Get-TreeFingerprint $tagPath)) {
        throw "The prepared tag '$Version' does not match trunk. Run 'svn revert -R .' and retry."
    }

    Invoke-Svn status
    Invoke-Svn diff --summarize

    if ($Commit) {
        Invoke-Svn commit -m "Release $Version"
    }
    else {
        Write-Host "Local SVN release '$Version' is prepared. Review the output, then publish with: svn commit -m `"Release $Version`" --username slabsit"
    }
}
finally {
    Pop-Location
}
