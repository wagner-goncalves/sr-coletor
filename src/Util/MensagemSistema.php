<?php
    namespace SR\Util;

	class MensagemSistema{
		public $mensagens = array(
			"ERR_ABRIR_ARQUIVO" => "Erro ao abrir arquivo",
			"ERR_SALVAR_ARQUIVO" => "Erro ao salvar arquivo",
			"ERR_PARAMETRO_CONFIG" => "Parâmetros incorretos",
			"ERR_CONEXAO_BANCO" => "Erro ao conectar com o banco de dados",
			"MSG_MENSAGEM" => "Texto não encontrado",
			"SUS_PROCESSAMENTO" => "Processamento realizado com sucesso.",
			"SUS_DOWNLOAD" => "Download realizado com sucesso.",
            "SUS_NOTIFICACAO" => "Notificações criadas com sucesso.",
			"ERR_DOWNLOAD" => "Erro ao realizar download.",
			"ERR_PROCESSAMENTO" => "Erro ao processar arquivo.",
            "ERR_NOTIFICACAO" => "Erro ao criar notificações.",
            "ERR_SEM_REGISTRO_DOWNLOAD" => "Nenhum registro encontrado para download.",
		);
		
		public static function get($chave){
			$objMensagemSistema = new MensagemSistema();
			if(array_key_exists($chave, $objMensagemSistema->mensagens)) return $objMensagemSistema->mensagens[$chave];
			else return $objMensagemSistema->mensagens["MSG_MENSAGEM"];
		}
	}
