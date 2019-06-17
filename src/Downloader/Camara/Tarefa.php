<?php
	namespace SR\Downloader\Camara;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
    use Firebase\JWT\JWT;
    
    class Tarefa
    {
		protected $container;

		public function __construct($container){
			$this->container = $container;
		}	
		
        //Recupera lista de tarefas
		public function getAll(ServerRequestInterface $request, ResponseInterface $response, array $args){
            
            try{
                $tasks = $this->container->db->select("tarefa", "*", [
                    "ORDER" => ["ordem" => "DESC"]
                ]);
                
                $error = $this->container->db->error();				
                if(intval($error[0]) > 0) throw new \Exception("Erro ao recuperar tarefas." . $error[2]);    
                
                return $response->withJson(["success" => true, "tarefas" => $tasks]);	
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }  	
        }
        
		public function addlog(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{

                $tarefa = [
                    "oidTarefa" => intval($request->getParam("oidTarefa")), 
                    "resposta" => $request->getParam("resposta"), 
                    "dataHoraInicio" => $request->getParam("dataHoraInicio"), 
                    "dataHoraFim" => $request->getParam("dataHoraFim")
                ];
    
                $tarefa["oidLogTarefa"] = $this->container->db->insert("logtarefa", $tarefa);
				
				$error = $this->container->db->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao incluir log da tarefa");				
				
                return $response->withJson(["success" => true, "tarefa" => $tarefa]);					

            }catch(\Exception $e){
                return $response->withStatus(500)->withJson(["success" => false, "message" => $e->getMessage()]);
            }                
        }          

		
    }
    