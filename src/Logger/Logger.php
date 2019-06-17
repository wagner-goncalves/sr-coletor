<?php
    namespace SR\Logger;

	class Logger{
		private $logs = array("Erro" => array(), "Sucesso" => array());

		public function addErro(Erro $erro, $tabela = "camara_logger"){
            $erro = $erro->salvaLog($tabela);
			$this->logs["Erro"][] = $erro;
            return $erro;
		}
		
		public function addSucesso(Sucesso $sucesso, $tabela = "camara_logger"){
            $sucesso = $sucesso->salvaLog($tabela);
			$this->logs["Sucesso"][] = $sucesso;
            return $sucesso;
		}
		
		public function getErros($print = false){
			if($print){ 
				foreach($this->logs["Erro"] as $erro) echo $erro->mensagem . "\n";
			}
			return $this->logs["Erro"];
		}
		
		public function getSucessos($print = false){
			if($print){ 
				foreach($this->logs["Sucesso"] as $sucesso){ 
					echo $sucesso->mensagem . "\n";
				}
			}
			return $this->logs["Sucesso"];
		}	
		
		public function getMensagens($print = false){
			$objErros = $this->getErros($print);
			$objSucessos = $this->getSucessos($print);
			return array_merge($objErros, $objSucessos);
		}	
		
	}
?>