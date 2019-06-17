<?php
    namespace SR\Util;

	class Util{
		public static function escapaBarraInvertida($chave){
			return str_replace("\\", "/", $chave);
		}
	}
