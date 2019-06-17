<?php
    namespace SR\Logger;
	
	use SR\Config\Config;

	class Log{
		public $oidLogger, $arquivo, $linha, $codigo, $mensagem, $trace, $flgTipoProcessamento, $flgSucesso, $codProcessamento, $dataHoraInicio, $dataHoraFim;
        public $arquivos = [];
		
		public function getDB(){
			$objDB = new \medoo(Config::getDatabaseSettings());
			if(!$objDB) throw new \Exception(MensagemSistema::get("ERR_CONEXAO_BANCO"));
			else return $objDB;
		}			
		
		public function Log(){

		}
        
        public function salvaArquivo($arquivo, $tabela = "camara_logger_arquivo"){
            $objDB = $this->getDB();
            $arquivo["oidCamaraLoggerArquivo"] = $objDB->insert($tabela, $arquivo);	
            $this->arquivos[] = $arquivo;
            return $this;
        }
        
        public function salvaLog($tabela = "camara_logger"){
			$objDB = $this->getDB();
			$this->oidLogger = $objDB->insert($tabela, array(
				"codProcessamento" => $this->codProcessamento, 
                "flgTipoProcessamento" => $this->flgTipoProcessamento,                 
				"arquivo" => $this->arquivo, 
				"linha" => $this->linha, 
				"codigo" => $this->codigo,
				"flgSucesso" => $this->flgSucesso, 
				"mensagem" => $this->mensagem, 
				"trace" => $this->trace, 
				"dataHoraInicio" => $this->dataHoraInicio == "" ? date("Y-m-d H:i:s") : $this->dataHoraInicio, 
				"dataHoraFim" => $this->dataHoraFim == "" ? date("Y-m-d H:i:s") : $this->dataHoraFim));
            return $this;
        }        
	}
?>