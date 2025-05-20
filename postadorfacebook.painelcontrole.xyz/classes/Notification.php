<?php
/**
 * Classe para gerenciamento de notificações
 * 
 * Esta classe permite criar e gerenciar notificações para os usuários
 */
class Notification {
    private $db;
    
    /**
     * Construtor
     * @param mysqli $db Conexão com o banco de dados
     */
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Cria uma nova notificação
     * 
     * @param int $usuario_id ID do usuário
     * @param string $tipo Tipo da notificação (sistema, campanha, conta)
     * @param string $titulo Título da notificação
     * @param string $mensagem Mensagem da notificação
     * @param string $link Link opcional
     * @return int ID da notificação criada ou false em caso de erro
     */
    public function criar($usuario_id, $tipo, $titulo, $mensagem, $link = null) {
        $query = "INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, link) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("issss", $usuario_id, $tipo, $titulo, $mensagem, $link);
        
        if ($stmt->execute()) {
            return $stmt->insert_id;
        }
        
        return false;
    }
    
    /**
     * Cria uma notificação para todos os usuários
     * 
     * @param string $tipo Tipo da notificação (sistema, campanha, conta)
     * @param string $titulo Título da notificação
     * @param string $mensagem Mensagem da notificação
     * @param string $link Link opcional
     * @return bool Sucesso ou falha
     */
    public function criarParaTodos($tipo, $titulo, $mensagem, $link = null) {
        $query = "SELECT id FROM usuarios";
        $result = $this->db->query($query);
        
        $success = true;
        
        while ($row = $result->fetch_assoc()) {
            $success = $success && $this->criar($row['id'], $tipo, $titulo, $mensagem, $link);
        }
        
        return $success;
    }
    
    /**
     * Cria uma notificação para todos os usuários de um grupo específico
     * 
     * @param array $usuario_ids Array com IDs de usuários
     * @param string $tipo Tipo da notificação (sistema, campanha, conta)
     * @param string $titulo Título da notificação
     * @param string $mensagem Mensagem da notificação
     * @param string $link Link opcional
     * @return bool Sucesso ou falha
     */
    public function criarParaGrupo($usuario_ids, $tipo, $titulo, $mensagem, $link = null) {
        $success = true;
        
        foreach ($usuario_ids as $id) {
            $success = $success && $this->criar($id, $tipo, $titulo, $mensagem, $link);
        }
        
        return $success;
    }
    
    /**
     * Marca uma notificação como lida
     * 
     * @param int $id ID da notificação
     * @param int $usuario_id ID do usuário
     * @return bool Sucesso ou falha
     */
    public function marcarComoLida($id, $usuario_id) {
        $query = "UPDATE notificacoes SET lida = 1 WHERE id = ? AND usuario_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $id, $usuario_id);
        
        return $stmt->execute();
    }
    
    /**
     * Conta o número de notificações não lidas
     * 
     * @param int $usuario_id ID do usuário
     * @return int Número de notificações não lidas
     */
    public function contarNaoLidas($usuario_id) {
        $query = "SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = ? AND lida = 0";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc()['total'];
    }
    
    /**
     * Exclui uma notificação
     * 
     * @param int $id ID da notificação
     * @param int $usuario_id ID do usuário
     * @return bool Sucesso ou falha
     */
    public function excluir($id, $usuario_id) {
        $query = "DELETE FROM notificacoes WHERE id = ? AND usuario_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $id, $usuario_id);
        
        return $stmt->execute();
    }
    
    /**
     * Exclui todas as notificações de um usuário
     * 
     * @param int $usuario_id ID do usuário
     * @return bool Sucesso ou falha
     */
    public function excluirTodas($usuario_id) {
        $query = "DELETE FROM notificacoes WHERE usuario_id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $usuario_id);
        
        return $stmt->execute();
    }
    
    /**
     * Obtém as notificações mais recentes de um usuário
     * 
     * @param int $usuario_id ID do usuário
     * @param int $limit Limite de notificações
     * @return array Lista de notificações
     */
    public function obterRecentes($usuario_id, $limit = 5) {
        $query = "SELECT * FROM notificacoes WHERE usuario_id = ? ORDER BY criado_em DESC LIMIT ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("ii", $usuario_id, $limit);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $notificacoes = [];
        
        while ($row = $result->fetch_assoc()) {
            $notificacoes[] = $row;
        }
        
        return $notificacoes;
    }
}
?>