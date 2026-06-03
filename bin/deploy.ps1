# ============================================================
# bin/deploy.ps1 — Pipeline de deploy local → produção
# ============================================================
# POR QUE EXISTE:
#   Antes desse script, deploy era: editar local, scp arquivo solto, esquecer
#   de rebuildar CSS, esquecer de rodar migration, esquecer de commitar.
#   Resultado: prod e git ficavam dessincronizados, bugs apareciam só em prod.
#   Esse pipeline garante que TODO deploy passa pelas mesmas etapas:
#
#     1. Verifica que não há arquivos não commitados (aborta se tiver).
#     2. Rebuilda Tailwind (npm run build).
#     3. Se o CSS mudou, faz commit "chore: rebuild css" automaticamente.
#     4. Faz git push pro remote.
#     5. SSH na prod: git pull + php bin/migrate.php (aplica pendentes).
#     6. Mostra status final do migrate em prod.
#
# USO:
#   pwsh bin/deploy.ps1                  # pipeline completo
#   pwsh bin/deploy.ps1 -SkipBuild       # pula rebuild Tailwind (já está atualizado)
#   pwsh bin/deploy.ps1 -DryRun          # mostra o que faria, sem executar
#
# REQUISITOS:
#   - PowerShell 5+ (já vem com Windows 10+)
#   - Chave SSH configurada para o usuário Hostinger
#   - Estar na branch que você quer deployar (geralmente main)
# ============================================================

[CmdletBinding()]
param(
    [switch]$SkipBuild,
    [switch]$DryRun,
    [switch]$ForceReset
)

$ErrorActionPreference = 'Stop'

# Caminhos / config — lidos de bin/deploy.config.ps1 (gitignored)
$configFile = Join-Path $PSScriptRoot 'deploy.config.ps1'
if (-not (Test-Path $configFile)) {
    Die "Arquivo $configFile não encontrado. Copie bin/deploy.config.example.ps1 e preencha com seus dados."
}
. $configFile

function Step($msg) { Write-Host "==> $msg" -ForegroundColor Cyan }
function Ok($msg)   { Write-Host "    $msg" -ForegroundColor Green }
function Warn($msg) { Write-Host "    $msg" -ForegroundColor Yellow }
function Die($msg)  { Write-Host "ERRO: $msg" -ForegroundColor Red; exit 1 }

# 1. Vai pra raiz do projeto (uma pasta acima de bin/)
$repoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repoRoot
Step "Diretório do projeto: $repoRoot"

# 2. Garante que está num repo git limpo
Step 'Verificando git status'
$gitStatus = git status --porcelain
if ($gitStatus) {
    Write-Host $gitStatus -ForegroundColor Yellow
    Die "Há arquivos não commitados. Faça commit/stash antes de deployar."
}
$branch = (git rev-parse --abbrev-ref HEAD).Trim()
Ok "Branch atual: $branch (workdir limpo)"

# 3. Rebuild do Tailwind
if ($SkipBuild) {
    Warn 'Pulando rebuild Tailwind (-SkipBuild)'
} else {
    Step 'Rebuildando Tailwind CSS (npm run build)'
    if ($DryRun) {
        Warn 'DRY-RUN: npm run build'
    } else {
        npm run build
        if ($LASTEXITCODE -ne 0) { Die 'npm run build falhou.' }
    }

    # Se o build modificou algum CSS, commita automaticamente
    $cssChanged = git status --porcelain public/assets/css/tailwind.css
    if ($cssChanged) {
        Step 'CSS mudou — commit automático do rebuild'
        if ($DryRun) {
            Warn 'DRY-RUN: git add + git commit "chore: rebuild css"'
        } else {
            git add public/assets/css/tailwind.css
            git commit -m 'rebuild css'
            if ($LASTEXITCODE -ne 0) { Die 'commit do CSS falhou.' }
        }
    } else {
        Ok 'CSS já estava atualizado.'
    }
}

# 4. Push pro remote
Step "git push origin $branch"
if ($DryRun) {
    Warn "DRY-RUN: git push origin $branch"
} else {
    git push origin $branch
    if ($LASTEXITCODE -ne 0) { Die 'git push falhou.' }
}

# 5. SSH na prod: pull + migrate
$sshTarget = "${RemoteUser}@${RemoteHost}"
$remoteCmd = if ($ForceReset) {
    "cd $RemotePath && git fetch origin && git reset --hard origin/main && php bin/migrate.php"
} else {
    "cd $RemotePath && git pull --ff-only && php bin/migrate.php"
}
Step "SSH em prod: git pull + migrate"
Write-Host "    $sshTarget : $remoteCmd" -ForegroundColor DarkGray
if ($DryRun) {
    Warn "DRY-RUN: ssh -p $RemotePort $sshTarget '$remoteCmd'"
} else {
    & ssh -p $RemotePort $sshTarget $remoteCmd
    if ($LASTEXITCODE -ne 0) { Die 'comando remoto falhou.' }
}

# 6. Status final em prod
Step 'Status final das migrations em prod'
if ($DryRun) {
    Warn "DRY-RUN: ssh -p $RemotePort $sshTarget 'cd $RemotePath && php bin/migrate.php --status'"
} else {
    & ssh -p $RemotePort $sshTarget "cd $RemotePath && php bin/migrate.php --status"
}

Step 'Deploy concluído.'
