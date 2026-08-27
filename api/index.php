<?php

require __DIR__ . '/../vendor/autoload.php';

use Vluzrmos\Precodahora\Client;
use Vluzrmos\Precodahora\Exceptions\ValidationException;
use Vluzrmos\Precodahora\Queries\ProdutoQuery;

// Configura a resposta para ser JSON
header('Content-Type: application/json; charset=utf-8');

// Permite requisições de outras origens (CORS)
header('Access-Control-Allow-Origin: *');

$client = new Client();

// Pega os parâmetros da URL, ou usa valores padrão
$codigoIBGE = isset($_GET['codigo']) ? (int) $_GET['codigo'] : 2914802;
$termo = isset($_GET['termo']) ? (string) $_GET['termo'] : 'feijao fradinho';

try {
    $municipio = $client->municipios()->findByCodigoIBGE($codigoIBGE);

    $query = (new ProdutoQuery())
        ->termo($termo)
        ->latitude($municipio?->latitude)
        ->longitude($municipio?->longitude)
        ->ordenarPorDistancia();

    $response = $client->produto($query);
    
    echo json_encode([
        'municipio' => $municipio,
        'resultados' => $response->resultado ?? []
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (ValidationException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getErrors()->all()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
