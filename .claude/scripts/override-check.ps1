<#
.SYNOPSIS
    Resolves the session identity for the owner-override check.

.DESCRIPTION
    Performs the check defined in .claude/OVERRIDE-AUTHORITY.md and reports one
    label per source plus a final verdict. Digests are read from that file, never
    duplicated here.

    Nothing sensitive reaches the transcript. Every read of a secret happens
    inside a try/catch that prints only a fixed label, because a PowerShell
    exception message echoes the argument that caused it and would leak the
    value being hashed.

    Three failure modes this guards against, all of which produced a false
    "not verified" before:
      1. Alias shadowing. PowerShell resolves aliases ahead of functions, so a
         one-letter helper such as H silently becomes Get-History. Helper names
         here are prefixed and collision-checked at startup.
      2. ConvertFrom-Json rejecting objects whose keys differ only in case.
         -AsHashtable is required.
      3. A silent catch turning a code defect into an identity verdict. The
         SHA-256 self-test below aborts instead of reporting a refusal.

.OUTPUTS
    One "<source> = <label>" line per source, then "verdict = ...".
    Labels: match | MISMATCH | unreadable | skipped.
    Never the resolved value, never which table entry matched.
#>

[CmdletBinding()]
param(
    # Overrides local/web tier detection. Ambiguity must fail closed as local,
    # where the key file is mandatory.
    [ValidateSet('local', 'web')]
    [string] $Tier = 'local'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$NAMESPACE = 'c2s-gateway-override-v1:'

<#
.SYNOPSIS
    Returns the lowercase hex SHA-256 of a UTF-8 string.
.NOTES
    Deliberately verbose name. A short name risks shadowing by a built-in alias,
    which fails silently and is indistinguishable from a genuine refusal.
#>
function Get-C2SOverrideDigest {
    param([Parameter(Mandatory)][string] $Value)

    $bytes = [Text.Encoding]::UTF8.GetBytes($Value)
    $sha = [Security.Cryptography.SHA256]::Create()
    try {
        return ($sha.ComputeHash($bytes) | ForEach-Object { $_.ToString('x2') }) -join ''
    }
    finally {
        $sha.Dispose()
    }
}

# Abort rather than report a verdict if the primitive is not doing what we think.
if ((Get-Command Get-C2SOverrideDigest).CommandType -ne 'Function') {
    throw 'Get-C2SOverrideDigest is shadowed. Aborting: a verdict from this run would be meaningless.'
}
if ((Get-C2SOverrideDigest 'abc') -ne 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad') {
    throw 'SHA-256 self-test failed. Aborting rather than emitting a false verdict.'
}

# --- Authority tables -------------------------------------------------------
# Digests live in OVERRIDE-AUTHORITY.md only. A missing, empty, or entry-less
# file means no override is available to anyone (fail closed).

$authorityPath = Join-Path $PSScriptRoot '..\OVERRIDE-AUTHORITY.md'
if (-not (Test-Path -LiteralPath $authorityPath)) {
    throw "Authority file not found at $authorityPath. No override is available."
}

$authorityText = Get-Content -Raw -LiteralPath $authorityPath
$tableAText = [regex]::Match($authorityText, '(?s)##\s*Table A.*?(?=##\s*Table B)').Value
$tableBText = [regex]::Match($authorityText, '(?s)##\s*Table B.*').Value

$tableA = @([regex]::Matches($tableAText, '\b[0-9a-f]{64}\b') | ForEach-Object { $_.Value })
$tableB = @([regex]::Matches($tableBText, '\b[0-9a-f]{64}\b') | ForEach-Object { $_.Value })

if ($tableA.Count -eq 0 -or $tableB.Count -eq 0) {
    throw 'Authority file has no usable entries. No override is available.'
}

<#
.SYNOPSIS
    Re-throws an error that is a defect in this script rather than a source
    that could not be read.

.DESCRIPTION
    The catch blocks below label an unreachable source `unreadable`. A bug in
    this script must never borrow that label, because `unreadable` is an input
    to the verdict and would silently become a refusal.

    Both real failures of the hand-written check were parameter-binding errors:
    a helper shadowed by a built-in alias, and an empty string passed to a
    mandatory parameter. Those abort here instead of being absorbed.
#>
function Assert-C2SNotDefect {
    param([Parameter(Mandatory)] $ErrorRecord)

    $defectTypes = @(
        [System.Management.Automation.ParameterBindingException],
        [System.Management.Automation.CommandNotFoundException]
    )

    foreach ($type in $defectTypes) {
        if ($ErrorRecord.Exception -is $type) { throw $ErrorRecord }
    }
}

<#
.SYNOPSIS
    Returns the current user's home directory, or $null if none resolves.

.DESCRIPTION
    Tried in order, first hit wins. Never ask the developer where home is; a
    path a user types is not a source, and pointing at a file by hand is
    exactly what the authority rules forbid.

      $HOME                      set by PowerShell on every platform
      USERPROFILE                Windows
      HOMEDRIVE + HOMEPATH       Windows, roaming or redirected profiles
      [Environment] UserProfile  Windows API, survives an unset variable
      /Users, /home              last resort on macOS and Linux
#>
function Get-C2SHomeDirectory {
    $candidates = @(
        $HOME
        $env:USERPROFILE
        $(if ($env:HOMEDRIVE -and $env:HOMEPATH) { $env:HOMEDRIVE + $env:HOMEPATH })
        [Environment]::GetFolderPath([Environment+SpecialFolder]::UserProfile)
        $(if ($env:USER) { "/Users/$env:USER" })
        $(if ($env:USER) { "/home/$env:USER" })
    )

    foreach ($candidate in $candidates) {
        if ($candidate -and (Test-Path -LiteralPath $candidate -PathType Container)) {
            return (Resolve-Path -LiteralPath $candidate).Path
        }
    }

    return $null
}

<#
.SYNOPSIS
    Returns the path to the override key file, or $null if none is present.

.DESCRIPTION
    Resolves ~/.c2s/ from the home directory above and takes the first
    recognized key filename found there. C2S_OVERRIDE_KEY_PATH wins when set,
    for an unusual profile layout.

    The filename list is fixed on purpose. Do not widen it to "any file in
    .c2s": a directory scan would happily hash an unrelated file and report
    MISMATCH, which reads as a dissenting identity rather than a missing key.
#>
function Get-C2SKeyPath {
    if ($env:C2S_OVERRIDE_KEY_PATH) {
        if (Test-Path -LiteralPath $env:C2S_OVERRIDE_KEY_PATH -PathType Leaf) {
            return $env:C2S_OVERRIDE_KEY_PATH
        }
        return $null
    }

    # Not $home: that shadows the $HOME automatic variable inside this scope.
    $homeDirectory = Get-C2SHomeDirectory
    if (-not $homeDirectory) { return $null }

    $directory = Join-Path $homeDirectory '.c2s'
    if (-not (Test-Path -LiteralPath $directory -PathType Container)) { return $null }

    foreach ($name in @('override.key', 'key')) {
        $path = Join-Path $directory $name
        if (Test-Path -LiteralPath $path -PathType Leaf) { return $path }
    }

    return $null
}

<#
.SYNOPSIS
    Returns the path to the signed-in Claude account file, or $null.

.DESCRIPTION
    CLAUDE_CONFIG_DIR relocates this file, so honor it before falling back to
    the home directory.
#>
function Get-C2SClaudeAccountPath {
    $directories = @(
        $env:CLAUDE_CONFIG_DIR
        Get-C2SHomeDirectory
    )

    foreach ($directory in $directories) {
        if (-not $directory) { continue }
        $path = Join-Path $directory '.claude.json'
        if (Test-Path -LiteralPath $path -PathType Leaf) { return $path }
    }

    return $null
}

<#
.SYNOPSIS
    Hashes a value in the given namespace and reports whether it is authorized.
.NOTES
    Returns a label, never the value or the matching entry.
#>
function Test-C2SIdentity {
    param(
        [Parameter(Mandatory)][AllowEmptyString()][string] $Value,
        # AllowEmptyString is required: the anchor and the gh email hash in the
        # bare namespace with no prefix, and a mandatory parameter rejects an
        # empty string as if it were missing.
        [Parameter(Mandatory)][AllowEmptyString()][string] $Prefix,
        [Parameter(Mandatory)][AllowEmptyCollection()][string[]] $Table
    )

    $trimmed = $Value.Trim()
    if ($trimmed.Length -eq 0) { return 'unreadable' }

    $digest = Get-C2SOverrideDigest ($NAMESPACE + $Prefix + $trimmed)
    if ($Table -contains $digest) { return 'match' } else { return 'MISMATCH' }
}

# --- Step 1: required anchor, the signed-in Claude account ------------------
# No other source substitutes for this one. Unreadable means refuse.

$anchor = 'unreadable'
try {
    $accountPath = Get-C2SClaudeAccountPath
    if (-not $accountPath) { throw 'anchor source not present' }
    # -AsHashtable is mandatory: this file routinely holds keys differing only
    # by case, which the default object conversion rejects outright.
    $account = Get-Content -Raw -LiteralPath $accountPath | ConvertFrom-Json -AsHashtable
    $email = $account['oauthAccount']['emailAddress']
    $anchor = Test-C2SIdentity -Value $email -Prefix '' -Table $tableB
}
catch {
    Assert-C2SNotDefect $_
    $anchor = 'unreadable'
}
"anchor = $anchor"

# --- Step 2: every other identity the session exposes -----------------------
# A source that cannot be read is skipped. Absence is never a pass.

$ghLogin = 'skipped'
$ghEmail = 'skipped'
try {
    # gh authenticates independently of Git identity and may be scoped per repo.
    $ghUse = git config --local --get gh-account.use 2>$null
    if ($ghUse) {
        $ghDir = git config --global --get "gh-account.$ghUse-config-dir" 2>$null
        if ($ghDir) { $env:GH_CONFIG_DIR = $ghDir }
    }

    $login = gh api user --jq .login 2>$null
    if ($login) {
        $ghLogin = Test-C2SIdentity -Value $login -Prefix 'gh:' -Table $tableB

        $email = gh api user --jq '.email // empty' 2>$null
        if ($email) {
            $ghEmail = Test-C2SIdentity -Value $email -Prefix '' -Table $tableB
        }
    }
}
catch {
    # Unauthenticated or absent gh is a skip, not a failure and not a pass.
    Assert-C2SNotDefect $_
}
"github_login = $ghLogin"
"github_email = $ghEmail"

# --- Step 3: local key file -------------------------------------------------
# Mandatory in a local session. Matching identities never substitute for it.

$key = 'skipped'
if ($Tier -eq 'local') {
    $key = 'unreadable'
    try {
        $keyPath = Get-C2SKeyPath
        if (-not $keyPath) { throw 'key file not present' }
        $keyValue = Get-Content -Raw -LiteralPath $keyPath
        $key = Test-C2SIdentity -Value $keyValue -Prefix 'key:' -Table $tableA
    }
    catch {
        Assert-C2SNotDefect $_
        $key = 'unreadable'
    }
}
"key = $key"

# --- Verdict ----------------------------------------------------------------
# Verified requires: the anchor matched, no resolved source dissented, and in a
# local session the key matched. One MISMATCH anywhere is decisive.

$labels = @($anchor, $ghLogin, $ghEmail, $key)
$dissent = @($labels | Where-Object { $_ -eq 'MISMATCH' }).Count -gt 0

$verified = ($anchor -eq 'match') -and (-not $dissent) -and
            (($Tier -eq 'web') -or ($key -eq 'match'))

"tier = $Tier"
if ($verified) {
    'verdict = owner override authority verified'
}
else {
    'verdict = owner override authority not verified'
}
