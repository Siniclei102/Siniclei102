<?php
/**
 * API REST para o Sistema de Postagem Automática em Grupos do Facebook
 * 
 * Este arquivo atua como o ponto de entrada principal para a API,
 * processando todas as requisições e retornando respostas no formato JSON.
 * 
 * @version 1.0
 */

// Configurações iniciais
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Se for requisição OPTIONS, retornar apenas cabeçalhos (para CORS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Incluir arquivos necessários
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'ApiController.php';

// Obter conexão com o banco de dados
$db = Database::getInstance()->getConnection();

// Inicializar controlador da API
$api = new ApiController($db);

// Obter a URI requisitada
$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
$base_path = '/api';

// Extrair o caminho da requisição
$request_path = '';
if (strpos($request_uri, $base_path) !== false) {
    $request_path = substr($request_uri, strpos($request_uri, $base_path) + strlen($base_path));
}

// Remover query string se existir
if (strpos($request_path, '?') !== false) {
    $request_path = substr($request_path, 0, strpos($request_path, '?'));
}

// Remover barra final se existir
$request_path = rtrim($request_path, '/');

// Obter método HTTP
$request_method = $_SERVER['REQUEST_METHOD'];

// Obter parâmetros da requisição
$params = [];
if ($request_method === 'GET') {
    $params = $_GET;
} else if ($request_method === 'POST') {
    // Verificar se o conteúdo é JSON
    $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (strpos($content_type, 'application/json') !== false) {
        $json = file_get_contents('php://input');
        $params = json_decode($json, true) ?: [];
    } else {
        $params = $_POST;
    }
}

// Obter token de autenticação
$headers = getallheaders();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
$token = '';

// Extrair token do cabeçalho Authorization
if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    $token = $matches[1];
}

// Rotas que não necessitam de autenticação
$public_routes = [
    '/login',
    '/register'
];

// Verificar se a rota atual precisa de autenticação
$needs_auth = !in_array($request_path, $public_routes);

// Se a rota precisa de autenticação, verificar o token
if ($needs_auth) {
    $auth_result = $api->authenticate($token);
    
    if (!$auth_result['success']) {
        // Token inválido ou expirado
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => $auth_result['message']
        ]);
        exit;
    }
    
    // Adicionar usuário autenticado aos parâmetros
    $params['user_id'] = $auth_result['user_id'];
}

// Processar rota solicitada
try {
    switch ($request_path) {
        // Rotas públicas
        case '/login':
            if ($request_method === 'POST') {
                $result = $api->login($params);
            } else {
                throw new Exception("Método não permitido", 405);
            }
            break;
            
        case '/register':
            if ($request_method === 'POST') {
                $result = $api->register($params);
            } else {
                throw new Exception("Método não permitido", 405);
            }
            break;
            
        // Rotas que exigem autenticação
        
        // Dashboard
        case '/dashboard':
            if ($request_method === 'GET') {
                $result = $api->getDashboard($params);
            } else {
                throw new Exception("Método não permitido", 405);
            }
            break;
            
        // Grupos
        case '/grupos':
            if ($request_method === 'GET') {
                $result = $api->getGrupos($params);
            } elseif ($request_method === 'POST') {
                $result = $api->createGrupo($params);
            } else {
                throw new Exception("Método não permitido", 405);
            }
            break;
            
        // Grupo específico
        case preg_match('/^\/grupos\/(\d+)$/', $request_path, $matches) ? $request_path : '':
            $grupo_id = $matches[1];
            
            if ($request_method === 'GET') {
                $result = $api->getGrupo($grupo_id, $params);
            } elseif ($request_method === 'PUT') {
                $result = $api->updateGrupo($grupo_id, $params);
            } elseif ($request_method === 'DELETE') {
                $result = $api->deleteGrupo($grupo_id, $params);
            } else {
                throw new Exception("Método não permitido", 405);
            }
            break;
            
        // Campanhas
        case '/campanhas':
            if ($request_method === 'GET') {
                $result = $api->getCampanhas($params);
            } elseif ($request_method === 'POST') {
                $result = $api->createCampanha($params);
            } else {
                throw new Exception("Método não permitido", 405);
            }
            break;
            
        // Campanha específica
        case preg_match('/^\/campanhas\/(\d+)$/', $request_path, $matches) ? $request_path : '':
            $campanha_id = $matches[1];
            
            if ($request_method === 'GET') {
                $result = $api->getCampanha($campanha_id, $params);
            } elseif ($request_method === 'PUT') {
                $result = $api->updateCampanha($campanha_id, $params);
            } elseif ($request_method === 'DELETE') {
                $result = $api->deleteCampanha($campanha_id, $params);
            } else {
                throw new Exception("Método não permitido", 405);
            }
            break;
            
        // Anúncios
        case '/anuncios':
            if ($request_method === 'GET') {
                $result = $api->getAnuncios($params);
            } elseif ($request_method === 'POST') {
                $result = $api->createAnuncio($params);
            } else {
                throw new Exception("Método não permitido", 405);
            }
            break;
            
        // Anúncio específico
        case preg_match('/^\/anuncios\/(\d+)$/', $request_path, $matches) ? $request_path : '':
            $anuncio_id = $matches[1];
            
            if ($request_method === 'GET') {
                $result = $api->getAnuncio($anuncio_id, $params);
            } elseif ($request_method === 'PUT') {
                $result = $api->updateAnuncio($anuncio_id, $params);
            } elseif ($request_method === 'DELETE') {
                $result = $api->deleteAnuncio($anuncio_id, $params);
            } else {
                throw new Exception("Método não permitido", 405);
            }
            break;
            
        // Agendamentos
        case '/agendamentos':
            if ($request_method === 'GET') {
                $result = $api->getAgendamentos($params);
            } elseif ($request_method === 'POST') {
                $result = $api->createAgendamento($params);
            } else {
                throw new Exception("Método não permitido", 405);
            }
            break;
            
        // Agendamento específico
        case preg_match('/^\/agendamentos\/(\d+)$/', $request_path, $matches) ? $request_path : '':
            $agendamento_id = $matches[1];
            
            if ($request_method === 'GET') {
                $result = $api->getAgendamento($agendamento_id, $params);
            } elseif ($request_method === 'PUT') {
                $result = $api->updateAgendamento($agendamento_id, $params);
            } elseif ($request_method === 'DELETE') {
                $result = $api->deleteAgendamento($agendamento_id, $params);
            } else {
                throw new Exception("Método não permitido", 405);
            }
            break;
            
        // Relatórios
        case '/relatorios':
            if ($request_method === 'GET') {
                $result = $api->getRelatorios($params);
            } else {
                throw new Exception("Método não permitido", 405);
            }
            break;
            
        // Métricas
        case '/metricas':
            if ($request_method === 'GET') {
                $result = $api->getMetricas($params);
            } else {
                throw new Exception("Método não permitido", 405);
            }
            break;
            
        // Perfil do usuário
        case '/perfil':
            if ($request_method === 'GET') {
                $result = $api->getPerfil($params);
            } elseif ($request_method === 'PUT') {
                $result = $api->updatePerfil($params);
            } else {
                throw new Exception("Método não permitido", 405);
            }
            break;
            
        // Postagem imediata
        case '/postar':
            if ($request_method === 'POST') {
                $result = $api->postarAgora($params);
            } else {
                throw new Exception("Método não permitido", 405);
            }
            break;
            
        // Rota não encontrada
        default:
            throw new Exception("Endpoint não encontrado", 404);
    }
    
    // Retornar resultado
    http_response_code(isset($result['code']) ? $result['code'] : 200);
    echo json_encode($result);
    
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>