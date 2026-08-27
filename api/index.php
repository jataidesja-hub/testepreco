<?php
require __DIR__ . '/../vendor/autoload.php';

use Vluzrmos\Precodahora\Client;
use Vluzrmos\Precodahora\Exceptions\ValidationException;
use Vluzrmos\Precodahora\Queries\ProdutoQuery;

$codigoIBGE = isset($_GET['codigo']) ? (int) $_GET['codigo'] : null;
$termo = isset($_GET['termo']) ? (string) $_GET['termo'] : null;

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
        $response = $client->produto($query);
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
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; color: #333; }
        header { background: #1a73e8; color: white; padding: 20px; text-align: center; }
        header h1 { font-size: 1.8rem; }
        header p { font-size: 0.95rem; opacity: 0.85; margin-top: 4px; }
        .container { max-width: 900px; margin: 30px auto; padding: 0 16px; }
        form { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); display: flex; gap: 12px; flex-wrap: wrap; }
        form input { flex: 1; min-width: 180px; padding: 12px 16px; border: 1px solid #ccc; border-radius: 8px; font-size: 1rem; }
        form button { padding: 12px 24px; background: #1a73e8; color: white; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; }
        form button:hover { background: #1558c0; }
        .info { background: #e8f0fe; border-left: 4px solid #1a73e8; padding: 12px 16px; border-radius: 8px; margin: 20px 0; font-size: 0.95rem; }
        .erro { background: #fce8e6; border-left: 4px solid #d93025; padding: 12px 16px; border-radius: 8px; margin: 20px 0; }
        .card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
        .card-info h3 { font-size: 1rem; color: #1a73e8; margin-bottom: 4px; }
        .card-info p { font-size: 0.88rem; color: #666; margin-top: 2px; }
        .card-preco { text-align: right; white-space: nowrap; }
        .card-preco .preco { font-size: 1.5rem; font-weight: bold; color: #34a853; }
        .card-preco .data { font-size: 0.8rem; color: #999; margin-top: 4px; }
        .nenhum { text-align: center; padding: 40px; color: #999; }
    </style>
</head>
<body>
<header>
    <h1>🛒 Preço da Hora</h1>
    <p>Consulte preços de produtos nos supermercados da Bahia</p>
</header>
<div class="container">
    <form method="GET">
        <input type="number" name="codigo" placeholder="Código IBGE da cidade" value="<?= htmlspecialchars($_GET['codigo'] ?? '2914802') ?>" required>
        <input type="text" name="termo" placeholder="Produto (ex: arroz, feijão...)" value="<?= htmlspecialchars($_GET['termo'] ?? '') ?>" required>
        <button type="submit">Buscar</button>
    </form>

    <?php if ($erro): ?>
        <div class="erro">❌ Erro: <?= htmlspecialchars($erro) ?></div>
    <?php elseif ($municipio): ?>
        <div class="info">📍 Município: <strong><?= htmlspecialchars($municipio->localidade) ?></strong> — <?= count($resultados) ?> resultado(s) encontrado(s)</div>

        <?php if (empty($resultados)): ?>
            <div class="nenhum">Nenhum produto encontrado.</div>
        <?php else: ?>
            <?php foreach ($resultados as $r): ?>
                <?php $p = $r->produto; $e = $r->estabelecimento; ?>
                <div class="card">
                    <div class="card-info">
                        <h3><?= htmlspecialchars($p->descricao ?? '') ?></h3>
                        <p><strong><?= htmlspecialchars($e->nomeEstabelecimento ?? '') ?></strong></p>
                        <p><?= htmlspecialchars($e->endLogradouro ?? '') ?>, <?= htmlspecialchars($e->endNumero ?? '') ?> — <?= htmlspecialchars($e->bairro ?? '') ?></p>
                        <p><?= htmlspecialchars($e->municipio ?? '') ?>/<?= htmlspecialchars($e->uf ?? '') ?> | Tel: <?= htmlspecialchars($e->telefone ?? '') ?></p>
                    </div>
                    <div class="card-preco">
                        <div class="preco">R$ <?= number_format((float)($p->precoUnitario ?? 0), 2, ',', '.') ?></div>
                        <div class="data"><?= htmlspecialchars($p->data ?? '') ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
