<?php

    namespace SR\Downloader;
	
	use SR\Util\MensagemSistema;

	class HttpReader{
		
		private $url = "";
		private $httpResult = "";
		
		public function __construct($url = ""){
			$this->url = $url;
		}

		public function getHttpResult(){
			return $this->httpResult;
		}
		
		public function urlReader($url = ""){        
			$ch = curl_init();
			if($url == "") $url = $this->url;
			curl_setopt($ch, CURLOPT_URL, $url);
			//curl_setopt($ch, CURLOPT_HEADER, true);	
			//curl_setopt($ch, CURLOPT_USERAGENT, "User-Agent: curl/7.39.0");	
			curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.87 Safari/537.36");	
			curl_setopt($ch, CURLOPT_FAILONERROR, true);	
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);	
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);	
			//curl_setopt($ch, CURLOPT_TIMEOUT, 15);
			$retorno = curl_exec($ch);
			if(curl_errno($ch)) throw new \Exception(curl_error($ch));
			curl_close($ch);
			$this->httpResult =  $retorno;
			return $retorno;
		}
		
		public function xmlReader($url = ""){
			$retorno = $this->urlReader($url);
			return $this->parseXml($retorno);
		}

		public function parseXml($xml){
			$this->httpResult =  $xml;
			libxml_use_internal_errors(TRUE);
			$objXml = new \SimpleXMLElement($xml);
			return $objXml;
		}
		
		public function urlSave($destino, $url = ""){
			$conteudo = $this->urlReader($url);
			$handle = fopen($destino, "w");
			if(!$handle) throw new \Exception(MensagemSistema::get("ERR_SALVAR_ARQUIVO"));
			fwrite($handle, $conteudo);
			fclose($handle);
			return $this->parseXml($conteudo);
		}
		
		public function lerArquivo($arquivo){
			$handle = fopen($arquivo, "r");
			if(!$handle) throw new \Exception(MensagemSistema::get("ERR_ABRIR_ARQUIVO"));
			$conteudo = fread($handle, filesize($arquivo));
			fclose($handle);
			return $conteudo;
		}		
	}

?>