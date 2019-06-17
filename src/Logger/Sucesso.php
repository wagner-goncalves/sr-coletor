<?php
    namespace SR\Logger;

	class Sucesso extends Log{
		public function __construct($codProcessamento, $flgTipoProcessamento, $arquivo, $linha, $codigo, $flgSucesso, $mensagem, $trace = "", $dataHoraInicio = "", $dataHoraFim = ""){
			$e = new \Exception();
            
            $this->codProcessamento = $codProcessamento;
            $this->flgTipoProcessamento = $flgTipoProcessamento;
			$this->arquivo = $arquivo;
			$this->linha = $linha;
			$this->codigo = $codigo;
            $this->flgSucesso = $flgSucesso;
			$this->mensagem = $mensagem;
			$this->trace = $trace == "" ? $e->getTraceAsString() : $trace;
            $this->dataHoraInicio = $dataHoraInicio == "" ? date("Y-m-d H:i:s") : $dataHoraInicio;
            $this->dataHoraFim = $dataHoraFim == "" ? date("Y-m-d H:i:s") : $dataHoraFim;
		}
	}
?>