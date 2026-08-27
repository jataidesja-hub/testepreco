<?php
require __DIR__ . '/../vendor/autoload.php';

use Vluzrmos\Precodahora\Client;
use Vluzrmos\Precodahora\Exceptions\ValidationException;
use Vluzrmos\Precodahora\Queries\ProdutoQuery;

// Endpoint AJAX: retorna lista de municípios para autocomplete
if (isset($_GET['autocomplete'])) {
    header('Content-Type: application/json; charset=utf-8');
    $q = $_GET['autocomplete'] ?? '';
    if (strlen($q) < 2) { echo '[]'; exit; }
    try {
        $client = new Client();
        $municipios = $client->municipios()->findByLocalidade($q);
        $out = array_slice(array_map(fn($m) => [
            'codigo' => $m->codigoIBGE,
            'nome'   => $m->localidade
        ], $municipios), 0, 10);
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo '[]';
    }
    exit;
}

$codigoIBGE = isset($_GET['codigo']) ? (int) $_GET['codigo'] : null;
$termo       = isset($_GET['termo'])  ? (string) $_GET['termo']  : null;
$cidadeNome  = isset($_GET['cidade']) ? (string) $_GET['cidade'] : null;

$municipio = null;
$resultados = [];
$erro = null;

if ($codigoIBGE && $termo) {
    try {
        $client = new Client();
        $municipio = $client->municipios()->findByCodigoIBGE($codigoIBGE);
        $query = (new ProdutoQuery())
            ->termo($termo)
            ->latitude($municipio?->latitude)
            ->longitude($municipio?->longitude)
            ->ordenarPorDistancia();
        $response  = $client->produto($query);
        $resultados = $response->resultado ?? [];
    } catch (ValidationException $e) {
        $erro = implode(', ', $e->getErrors()->all());
    } catch (Exception $e) {
        $erro = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Preço da Hora</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f4f8;color:#333}
header{background:#1a73e8;color:#fff;padding:22px;text-align:center}
header h1{font-size:1.8rem}
header p{font-size:.9rem;opacity:.85;margin-top:4px}
.container{max-width:960px;margin:30px auto;padding:0 16px}
form{background:#fff;padding:24px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start}
.field{position:relative;flex:1;min-width:200px}
.field input{width:100%;padding:12px 16px;border:1px solid #ccc;border-radius:8px;font-size:1rem}
.field input:focus{outline:none;border-color:#1a73e8}
.suggestions{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ccc;border-top:none;border-radius:0 0 8px 8px;z-index:100;max-height:220px;overflow-y:auto}
.suggestions li{padding:10px 16px;cursor:pointer;list-style:none;font-size:.95rem}
.suggestions li:hover{background:#e8f0fe}
form button{padding:12px 28px;background:#1a73e8;color:#fff;border:none;border-radius:8px;font-size:1rem;cursor:pointer;align-self:flex-start}
form button:hover{background:#1558c0}
.info{background:#e8f0fe;border-left:4px solid #1a73e8;padding:12px 16px;border-radius:8px;margin:20px 0;font-size:.95rem}
.erro{background:#fce8e6;border-left:4px solid #d93025;padding:12px 16px;border-radius:8px;margin:20px 0}
.card{background:#fff;border-radius:12px;padding:20px;margin-bottom:14px;box-shadow:0 2px 6px rgba(0,0,0,.08)}
.card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}
.prod-nome{font-size:1.05rem;font-weight:700;color:#1a73e8;margin-bottom:6px}
.tag{display:inline-block;font-size:.75rem;padding:2px 8px;border-radius:20px;margin-right:4px;margin-bottom:4px}
.tag-ncm{background:#e8f0fe;color:#1a73e8}
.tag-gtin{background:#fce8e6;color:#c5221f}
.preco{font-size:1.8rem;font-weight:700;color:#34a853;white-space:nowrap}
.preco-bruto{font-size:.85rem;color:#999;text-decoration:line-through}
.preco-desc{font-size:.8rem;color:#f29900;font-weight:600}
.divider{border:none;border-top:1px solid #eee;margin:14px 0}
.card-bot{display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:.87rem;color:#555}
.card-bot strong{color:#333}
.label{font-size:.75rem;text-transform:uppercase;color:#999;margin-bottom:2px}
.data{font-size:.78rem;color:#aaa;margin-top:4px}
.nenhum{text-align:center;padding:50px;color:#999}
</style>
</head>
<body>
<header>
    <h1>🛒 Preço da Hora</h1>
    <p>Consulte preços nos supermercados da Bahia</p>
</header>
<div class="container">
<form method="GET" id="formBusca">
    <div class="field">
        <input type="text" id="cidadeInput" name="cidade" placeholder="Cidade (ex: Salvador, Itabuna...)"
               value="<?= htmlspecialchars($cidadeNome ?? '') ?>" autocomplete="off" required>
        <input type="hidden" id="codigoInput" name="codigo" value="<?= htmlspecialchars($_GET['codigo'] ?? '') ?>">
        <ul class="suggestions" id="suggestions" style="display:none"></ul>
    </div>
    <div class="field">
        <input type="text" name="termo" placeholder="Produto (ex: arroz, leite, feijão...)"
               value="<?= htmlspecialchars($_GET['termo'] ?? '') ?>" required>
    </div>
    <button type="submit">Buscar</button>
</form>

<?php if ($erro): ?>
    <div class="erro">❌ <?= htmlspecialchars($erro) ?></div>
<?php elseif ($municipio): ?>
    <div class="info">
        📍 <strong><?= htmlspecialchars($municipio->localidade) ?></strong>
        &nbsp;|&nbsp; Lat: <?= $municipio->latitude ?> / Lon: <?= $municipio->longitude ?>
        &nbsp;|&nbsp; <?= count($resultados) ?> resultado(s)
    </div>

    <?php if (empty($resultados)): ?>
        <div class="nenhum">Nenhum produto encontrado.</div>
    <?php else: ?>
        <?php foreach ($resultados as $r): ?>
            <?php $p = $r->produto; $e = $r->estabelecimento; ?>
            <div class="card">
                <div class="card-top">
                    <div>
                        <div class="prod-nome"><?= htmlspecialchars($p->descricao ?? '') ?></div>
                        <?php if(!empty($p->ncmGrupo)): ?>
                            <span class="tag tag-ncm">NCM: <?= htmlspecialchars($p->ncmGrupo) ?></span>
                        <?php endif; ?>
                        <?php if(!empty($p->gtin)): ?>
                            <span class="tag tag-gtin">GTIN: <?= htmlspecialchars($p->gtin) ?></span>
                        <?php endif; ?>
                        <div class="data">Atualizado: <?= htmlspecialchars($p->data ?? '') ?> &nbsp;|&nbsp; Intervalo: <?= htmlspecialchars($p->intervalo ?? '') ?></div>
                    </div>
                    <div style="text-align:right">
                        <?php if(!empty($p->desconto)): ?>
                            <div class="preco-bruto">R$ <?= number_format((float)($p->precoBruto ?? 0), 2, ',', '.') ?></div>
                            <div class="preco-desc">-R$ <?= number_format((float)($p->desconto), 2, ',', '.') ?> desc.</div>
                        <?php endif; ?>
                        <div class="preco">R$ <?= number_format((float)($p->precoUnitario ?? 0), 2, ',', '.') ?></div>
                        <div style="font-size:.8rem;color:#999">Unidade: <?= htmlspecialchars($p->unidade ?? '') ?></div>
                    </div>
                </div>
                <hr class="divider">
                <div class="card-bot">
                    <div>
                        <div class="label">Estabelecimento</div>
                        <strong><?= htmlspecialchars($e->nomeEstabelecimento ?? '') ?></strong><br>
                        CNPJ: <?= htmlspecialchars($e->cnpj ?? '') ?><br>
                        Farmácia popular: <?= ($e->farmaciaPopular ?? false) ? 'Sim' : 'Não' ?>
                    </div>
                    <div>
                        <div class="label">Endereço</div>
                        <?= htmlspecialchars($e->endLogradouro ?? '') ?>, nº <?= htmlspecialchars($e->endNumero ?? '') ?><br>
                        <?= htmlspecialchars($e->bairro ?? '') ?> — CEP: <?= htmlspecialchars($e->cep ?? '') ?><br>
                        <?= htmlspecialchars($e->municipio ?? '') ?>/<?= htmlspecialchars($e->uf ?? '') ?>
                        &nbsp;|&nbsp; Tel: <?= htmlspecialchars($e->telefone ?? '') ?>
                    </div>
                    <div>
                        <div class="label">Localização</div>
                        Lat: <?= $e->latitude ?? '' ?><br>
                        Lon: <?= $e->longitude ?? '' ?><br>
                        Distância: <?= number_format((float)($e->distancia ?? 0), 4, ',', '.') ?> km
                    </div>
                    <div>
                        <div class="label">Produto</div>
                        NCM: <?= htmlspecialchars($p->ncm ?? '') ?><br>
                        Cód. NF-e: <?= htmlspecialchars($p->cod_nfce ?? '') ?><br>
                        Tipo NF-e: <?= htmlspecialchars($p->tipoNFe ?? '') ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>
</div>

<script>
const cidadeInput = document.getElementById('cidadeInput');
const codigoInput = document.getElementById('codigoInput');
const suggestions = document.getElementById('suggestions');
let timer;

cidadeInput.addEventListener('input', () => {
    clearTimeout(timer);
    codigoInput.value = '';
    const q = cidadeInput.value.trim();
    if (q.length < 2) { suggestions.style.display = 'none'; return; }
    timer = setTimeout(async () => {
        const res = await fetch(`?autocomplete=${encodeURIComponent(q)}`);
        const data = await res.json();
        suggestions.innerHTML = '';
        if (!data.length) { suggestions.style.display = 'none'; return; }
        data.forEach(m => {
            const li = document.createElement('li');
            li.textContent = m.nome;
            li.addEventListener('click', () => {
                cidadeInput.value = m.nome;
                codigoInput.value = m.codigo;
                suggestions.style.display = 'none';
            });
            suggestions.appendChild(li);
        });
        suggestions.style.display = 'block';
    }, 300);
});

document.addEventListener('click', e => {
    if (!e.target.closest('.field')) suggestions.style.display = 'none';
});

document.getElementById('formBusca').addEventListener('submit', e => {
    if (!codigoInput.value) {
        alert('Selecione uma cidade da lista de sugestões.');
        e.preventDefault();
    }
});
</script>
</body>
</html>
