(function () {
    'use strict';

    angular
        .module('SrCidadao')
        .controller('GraficosCtrl', Controller);

    function Controller($scope, $document, $interval, ReportService, NgMap, ConfigurationService, TarefaService) {

        var vm = this;
        vm.initController = initController; 
        vm.lastUser = lastUser; 
        vm.appUseLocation = appUseLocation; 
        vm.topLike = topLike; 
        
        //Variáveis      
        vm.mapLastUser = {};
        vm.mapAppUseLocation = {};
        vm.graphTopLike = {};         
        
        //Funções
        
        vm.initController();

        function initController() {
            vm.lastUser();
            vm.appUseLocation();
            vm.topLike();
        }
        
        function lastUser(){
            ReportService.lastUser(function(success, response){
                if(success && response.usuario){
                    vm.mapLastUser = response.usuario;
                }
            });
        }
        
        function appUseLocation(){            
            ReportService.appUseLocation({}, function(success, response){
                if(success && response.dados){
                    $scope.mapAppUseLocation = [];

                    angular.forEach(response.dados, function(value, key){
                        var point = new google.maps.LatLng(parseFloat(value.latitude), parseFloat(value.longitude));
                        $scope.mapAppUseLocation.push(point);
                    });
                    
                    var map = new google.maps.Map(document.getElementById("mapAppUseLocation"), {
                        zoom: 4,
                        center:{lat:-15.7941, lng: -47.8825}
                    }); 
                    var heatmap = new google.maps.visualization.HeatmapLayer({
                        data: $scope.mapAppUseLocation, 
                        map: map
                    });
                    
                    heatmap.setMap(map);
                }
            });
        }   
        
        function topLike(){
            ReportService.topLike({}, function(success, response){
                if(success){
                    vm.graphTopLike.labels = response.labels;
                    vm.graphTopLike.data = response.data;
                }
                console.log(response.dados);
            });
        }           

    }

})();