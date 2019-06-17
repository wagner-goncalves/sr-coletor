<?php

    namespace SR\Downloader;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;

    interface IDownloader{
        public function defineNomeArquivo($funcao, $codProcessamento, $adicionais);
        public function defineNomeRelativo($funcao, $codProcessamento, $adicionais);
        public function criarCodigoProcessamento(ServerRequestInterface $request, ResponseInterface $response, array $args);
    }
