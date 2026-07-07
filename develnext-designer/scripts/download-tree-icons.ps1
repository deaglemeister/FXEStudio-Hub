# Downloads IntelliJ New UI SVG icons for FXE file tree (Apache 2.0).
# Source: https://github.com/JetBrains/intellij-community/tree/master/platform/icons/src/expui
# Browse: https://intellij-icons.jetbrains.design/

$ErrorActionPreference = 'Stop'

$root = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$dst = Join-Path $root 'develnext-designer\src\main\resources\org\develnext\jphp\gui\designer\editor\tree\ui\tree'
$base = 'https://raw.githubusercontent.com/JetBrains/intellij-community/master/platform/icons/src/expui'

New-Item -ItemType Directory -Force -Path $dst | Out-Null

# @(localName, expuiFolder, remoteBaseName)
$icons = @(
    @('css', 'fileTypes', 'css'),
    @('xml', 'fileTypes', 'xml'),
    @('text', 'fileTypes', 'text'),
    @('properties', 'fileTypes', 'properties'),
    @('markdown', 'fileTypes', 'markdown'),
    @('sql', 'fileTypes', 'sql'),
    @('shell', 'fileTypes', 'shell'),
    @('config', 'fileTypes', 'config'),
    @('csv', 'fileTypes', 'csv'),
    @('toml', 'fileTypes', 'toml'),
    @('docker', 'fileTypes', 'docker'),
    @('gradle', 'fileTypes', 'gradle'),
    @('graphql', 'fileTypes', 'graphql'),
    @('patch', 'fileTypes', 'patch'),
    @('editorConfig', 'fileTypes', 'editorConfig'),
    @('groovy', 'fileTypes', 'groovy'),
    @('cpp', 'fileTypes', 'cpp'),
    @('Csharp', 'fileTypes', 'Csharp'),
    @('swiftLang', 'fileTypes', 'swiftLang'),
    @('terraform', 'fileTypes', 'terraform'),
    @('vue', 'fileTypes', 'vue'),
    @('jupyter', 'fileTypes', 'jupyter'),
    @('binaryData', 'fileTypes', 'binaryData'),
    @('font', 'fileTypes', 'font'),
    @('regexp', 'fileTypes', 'regexp'),
    @('jsonSchema', 'fileTypes', 'jsonSchema'),
    @('ideaProject', 'nodes', 'ideaProject')
)

$downloaded = 0
$skipped = 0

foreach ($item in $icons) {
    $local = $item[0]
    $folder = $item[1]
    $remote = $item[2]

    foreach ($variant in @('', '_dark')) {
        $fileName = if ($variant -eq '') { "$local.svg" } else { "${local}_dark.svg" }
        $remoteName = if ($variant -eq '') { "$remote.svg" } else { "${remote}_dark.svg" }
        $url = "$base/$folder/$remoteName"
        $out = Join-Path $dst $fileName

        try {
            Invoke-WebRequest -Uri $url -OutFile $out -UseBasicParsing
            $downloaded++
            Write-Host "OK $fileName"
        } catch {
            $skipped++
            Write-Host "SKIP $fileName ($url)"
        }
    }
}

Write-Host "Done: downloaded=$downloaded skipped=$skipped -> $dst"
