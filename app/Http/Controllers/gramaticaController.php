<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;

class gramaticaController extends Controller
{
    //
    var $variaveisList;

    public function processaArquivo(Request $req){
        //Leitura de arquivo
        $file = $req->file('file');
        $file = file_get_contents($file->getRealPath());
        
        $readed = json_decode($file);
    
        $glc = $readed->glc;
        //

        //Criação de variáveis
        $variaveisList = $this->criaListVariaveis();
        
        try{
            $variaveis = $glc[0];
            $simbolos = $glc[1];
            $regras = $glc[2];
            $regrasIni = $regras;
            $start = $glc[3];
            
            //Padroniza as entradas
            if(!is_array($simbolos)){
                $simbolos = array($simbolos);
            }

            if(!is_array($variaveis)){
                $variaveis = array($variaveis);
            }


            if(in_array('#', $variaveis)){
                return $this->preparaSaida($variaveis, $simbolos, $regrasIni, array(), $start);
            }


            $variaveisList = $this->atualizaVariaveisLivres($variaveis, $variaveisList);
            
            //Passo 0:
            //Procura regras com símbolos no segundo caractere e adiante.
            $resultPasso0 = $this->forcaRegrasFormaNormal($regras, $simbolos, $variaveis, $variaveisList);
            $variaveis = $resultPasso0['variaveis'];
            $regras = $resultPasso0['regras'];
            $variaveisList = $resultPasso0['variaveisList'];
            

            $indexesVariaveis = $this->geraIndexVariaveis($variaveis, $start);
            $variaveisList = $this->atualizaVariaveisLivres($variaveis, $variaveisList);

            $regras = $this->organizaRegras($regras, $indexesVariaveis);
            $regrasIni = $regras;

            //Passo 1:
            //Recursividade à resquerda
            $regrasProcessadas = $this->procuraRecursividadeEsquerda($regras, $simbolos, $variaveisList);
            if(isset($regrasProcessadas['regrasIni'])){
                $regrasIni = $regrasProcessadas['regrasIni'];
                $regras = $regrasProcessadas['novasRegras'];
            } 
            $variaveisList = $regrasProcessadas['variaveisList'];

            //Passo 2: Cima pra baixo
            //Percorre regras iniciais de cima pra baixo.
            $resultPasso2 = $this->atualizaRegrasUpToDown($regrasIni, $indexesVariaveis, $simbolos, $variaveisList, $regras, 0);
            $regrasIni = $resultPasso2['regrasIni'];
            $regrasExtras = $this->removeRegrasDuplicadas($resultPasso2['novasRegras']);
            $regrasIni = $this->organizaRegras($regrasIni, $indexesVariaveis);
            $variaveisList = $resultPasso2['variaveisList'];
            
            //Passo 3:
            $regrasPasso3 = $this->atualizaRegrasDownToUp($regrasIni, $indexesVariaveis, $simbolos);


            //Passo 4:
            $regrasExtras = $this->atualizaRegrasExtras($regrasExtras, $regrasPasso3, $simbolos);
            
            return $this->preparaSaida($variaveis, $simbolos, $regrasPasso3, $regrasExtras, $start);

        } catch(Exception $e){
            echo 'Erro em: '.$e;
        }

    }

    public function forcaRegrasFormaNormal($regras, $simbolos, $variaveis, $variaveisList){
        //Procura regra do tipo A -> BcD.
        //Se encontrar, cria variavel E -> cD, e faz A -> BE
        foreach($regras as $count => $regra){
            //$chars = str_split($regra[1]);
            foreach(str_split($regra[1]) as $pos => $letra){
                if($pos > 0 && in_array($letra, $simbolos)){
                    //Cria nova Variável
                    $novaVariavel = $this->criaVariavel($variaveisList);
                    $variaveisList = $novaVariavel['variaveisList'];
                    
                    //Atualiza as regras
                    $novaRegra = [$novaVariavel['newVar'], substr($regra[1], $pos)];
                    $regraAntiga = [$regra[0], substr($regra[1], 0, $pos).$novaVariavel['newVar']];
                    array_push($regras, $novaRegra);
                    array_push($regras, $regraAntiga);
                    unset($regras[$count]);
                    //Adicionar a variável na lista de variáveis oficiais
                    $variaveis[] = $novaVariavel['newVar'];
                }
            }
        }
        return [
            'regras' => $regras,
            'variaveis' => $variaveis,
            'variaveisList' => $variaveisList
        ];
    }

    public function preparaSaida($variaveis, $simbolos, $regrasIni, $regrasExtras, $start){
        foreach($regrasExtras as $regraExtra){
            $variaveis[] = $regraExtra[0];
        }
        $variaveis = array_values(array_unique($variaveis));
        $regras = array_merge($regrasIni, $regrasExtras);
        
        $glc['glc'] = [
            $variaveis,
            $simbolos,
            $regras,
            $start,
        ];

        return json_encode($glc);
    }

    public function atualizaRegrasExtras($regrasExtras, $regrasIni, $simbolos){
        //Converte as regras extras para a forma normal (formato constanteVariaveis)
        foreach($regrasExtras as $count => $regraExtra){
            if(!in_array(substr($regraExtra[1],0,1), $simbolos)){
                $variavelBusca = substr($regraExtra[1],0,1);
                $regrasFromVariavel = $this->buscaRegrasVariavel($variavelBusca, $regrasIni);
                foreach($regrasFromVariavel as $regraFromVariavel){
                    $regraReplace = [$regraExtra[0], ($regraFromVariavel[1].substr($regraExtra[1], 1))];
                    array_push($regrasExtras, $regraReplace);
                }
                unset($regrasExtras[$count]);
            }
        }
        return array_values($regrasExtras);
    }

    public function atualizaRegrasDownToUp($regras, $indexesVariaveis, $simbolos){
        //Se a regra começa com uma variável, faz a substituição

        $regras = array_reverse($regras);
        //Inverte as regras pra pegar de cima pra baixo
        foreach($regras as $count => $regra){
            if(!in_array(substr($regra[1], 0, 1), $simbolos)){
                $variavelBusca = substr($regra[1], 0, 1);
                $regrasFromVariavel = $this->buscaRegrasVariavel($variavelBusca, $regras);
                foreach($regrasFromVariavel as $regraFromVariavel){
                    $regraReplace = [$regra[0], ($regraFromVariavel[1].substr($regra[1], 1))];
                    array_push($regras, $regraReplace);
                }
                unset($regras[$count]);
                //dd($this->organizaRegras($regras, $indexesVariaveis));
            }
        }
        return $this->organizaRegras($regras, $indexesVariaveis);
    }

    public function atualizaRegrasUpToDown($regrasIni, $indexesVariaveis, $simbolos, $variaveisList, $regrasExtras, $countDebug){
        /*
        Percorre as regras de cima pra baixo e compara o primeiro caractere da regra com a origem da regra.
        Se for uma variável com índex menor que a origem da regra, faz uma substituição.
        Depois refaz uma comparação de recursividade à esquerda.
        */
        $regrasEntrada = $regrasIni;

        foreach($regrasIni as $count => $regra){
            //'A -> BC'
            $indexRegra = $this->getIndexVariavel($regra[0], $indexesVariaveis);
            if(!in_array(substr($regra[1], 0, 1), $simbolos)){
                $indexInicioRegra = $this->getIndexVariavel(substr($regra[1], 0 ,1), $indexesVariaveis);
                if($indexInicioRegra >= 0 && $indexRegra > $indexInicioRegra){
                    //exemplo C -> BB
                    $regrasFromVariavel = $this->buscaRegrasVariavel(substr($regra[1],0,1), $regrasIni);
                    if(!empty($regrasFromVariavel)){
                        foreach($regrasFromVariavel as $regraFromVariavel){
                            $regraAtualizada = [$regra[0] , $regraFromVariavel[1].substr($regra[1], 1 )];
                            array_push($regrasIni, $regraAtualizada);
                        }
                        unset($regrasIni[$count]);
                    }
                }
            }
        }
        $regrasIni = array_values($regrasIni);
        
        if($this->comparaListaRegras($regrasEntrada, $regrasIni) == false) {
            $regrasProcessadas = $this->procuraRecursividadeEsquerda($regrasIni, $simbolos, $variaveisList);
                
            if(isset($regrasProcessadas['regrasIni']) === false){
                //Não gerou recursividade
                $regrasExtras = $this->removeRegrasDuplicadas($regrasExtras);
                $novoUpToDown = $this->atualizaRegrasUpToDown($regrasIni, $indexesVariaveis, $simbolos, $regrasProcessadas['variaveisList'], $regrasExtras, $countDebug+1);
                return [
                    'regrasIni' => $novoUpToDown['regrasIni'],
                    'novasRegras' => array_merge($regrasExtras, $novoUpToDown['novasRegras']),
                    'variaveisList' => $regrasProcessadas['variaveisList'],
                ];
            } else {
                //Se gerou recursividade
                $regrasIni = $regrasProcessadas['regrasIni'];
                $novasRegras = $regrasProcessadas['novasRegras'];
                $regrasExtras = $this->removeRegrasDuplicadas(array_merge($regrasExtras, $novasRegras));
                $novoUpToDown = $this->atualizaRegrasUpToDown($regrasIni, $indexesVariaveis, $simbolos, $regrasProcessadas['variaveisList'], $regrasExtras, $countDebug+1);
                return [
                    'regrasIni' => $novoUpToDown['regrasIni'],
                    'novasRegras' => array_merge($regrasExtras, $novoUpToDown['novasRegras']),
                    'variaveisList' => $regrasProcessadas['variaveisList']
                ];
            }
            
        } else {
            return [
                'regrasIni' => $regrasIni,
                'novasRegras' => $regrasExtras, 
                'variaveisList' => $variaveisList,
            ];
        }
    }

    public function comparaListaRegras($listaRegras1, $listaRegras2){
        if(count($listaRegras1) !== count($listaRegras2)){
            return false;
        } else {
            $countItens = 0;
            foreach($listaRegras1 as $regraBase){
                foreach($listaRegras2 as $regraRef){
                    if(($regraBase[0] == $regraRef[0]) && ($regraBase[1] == $regraRef[1])){
                        $countItens++;
                    }
                }
            }
            if($countItens == count($listaRegras1)){
                return true;
            } else {
                return false;
            }
        }
    }

    public function organizaRegras($regras, $indexesVariaveis){
        $regrasOrdered = [];
        foreach($indexesVariaveis as $index){
            foreach($regras as $regra){
                if($regra[0] == key($index)){
                    $regrasOrdered[] = $regra;
                }
            }
        }
        return $regrasOrdered;
    }

    public function buscaRegrasVariavel($variavel, $regrasIni){
        $regras = [];
        foreach($regrasIni as $regra){
            if($regra[0] == $variavel){
                array_push($regras, $regra);
            }
        }
        return $regras;
    }

    public function getIndexVariavel($variavel, $indexesVariaveis){
        foreach($indexesVariaveis as $index){
            if(key($index) == $variavel) {
                return $index[$variavel];
            }
        }
        return -1;
    }

    public function procuraRecursividadeEsquerda($regras, $simbolos, $variaveisList){

        foreach($regras as $numero => $regra){
            $first_item = substr($regra[1], 0, 1) ;
            if(!in_array($first_item, $simbolos)){
                if($first_item == $regra[0]){
                    $variaveisResult = $this->criaVariavel($variaveisList);
                    $novaVariavel = $variaveisResult['newVar'];
                    //Atualizando a lista de variáveis disponíveis
                    $variaveisList = $variaveisResult['variaveisList'];
                    $novasRegras[] = [$novaVariavel, substr($regra[1], 1)];
                    $novasRegras[] = [$novaVariavel, substr($regra[1], 1).$novaVariavel];
                    
                    //atualizaRegraAntigaPassoRecursivo
                    $regrasIni = $this->atualizaRegraAntigaPassoRecursivo($first_item, $regras, $novaVariavel);

                    return [
                        'regrasIni' => $regrasIni,
                        'novasRegras' => $novasRegras,
                        'variaveisList' => $variaveisList
                    ];
                } else {
                    
                }
            }
        }
        return ['variaveisList' => $variaveisList];
    }

    public function atualizaRegraAntigaPassoRecursivo($variavel, $regras, $novaVariavel){
        $regrasNaoRecursivas = [];
        foreach($regras as $count => $regra){
            if($regra[0] == $variavel){
                if(substr($regra[1], 0, 1) !== $variavel){
                    $nrecursivaAdicionada = $regra;
                    $nrecursivaAdicionada[1] = $nrecursivaAdicionada[1].$novaVariavel;
                    array_push($regrasNaoRecursivas, $regra);
                    array_push($regrasNaoRecursivas, $nrecursivaAdicionada);
                    
                    unset($regras[$count]);
                } else {
                    unset($regras[$count]);
                }
            } 
        }
        foreach($regrasNaoRecursivas as $regraNaoRecursiva){
            array_push($regras, $regraNaoRecursiva);
        }
        $regras = $this->removeRegrasDuplicadas($regras);
        return $regras;
        
    }

    public function removeRegrasDuplicadas($regrasList){
        $duplicatas = [];
        $regrasList = array_values($regrasList);
        foreach($regrasList as $count => $regra){
            for($i = $count+1; $i<count($regrasList); $i++){
                if($regra[0] == $regrasList[$i][0] && $regra[1] == $regrasList[$i][1]){
                    $duplicatas[] = $i;
                }
            }
        }
        foreach($duplicatas as $duplicata){
            unset($regrasList[$duplicata]);
        }
        return array_values($regrasList);
    }

    public function criaListVariaveis() {
        //65 - 90 A-Z
        $vars = '';
        for($i = 65; $i<=90; $i++){
            $vars = $vars.chr($i);
        }
        return $vars;
    }

    public function atualizaVariaveisLivres($variaveis, $variaveisList){
        if(is_array($variaveis)){
            foreach($variaveis as $variavel){
                $variaveisList = str_replace($variavel, '', $variaveisList);
            }
        } else {
            $variaveisList = str_replace($variaveis, '', $variaveisList);
        }
        return $variaveisList;
    }

    public function criaVariavel($variaveisList){
        $newVar = substr($variaveisList, 0, 1);
        $variaveisList = $this->atualizaVariaveisLivres($newVar, $variaveisList);
        return [
            'newVar' => $newVar,
            'variaveisList' => $variaveisList,
        ];
    }

    public function geraIndexVariaveis($variaveis, $start){
        $indexes = [];
        //Força a posição inicial da variável de início
        if(in_array($start, $variaveis)){
            array_push($indexes, [$start => 0]);

            foreach($variaveis as $count => $variavel){
                if($variavel == $start){
                    unset($variaveis[$count]);
                }
            }
            $variaveis = array_values($variaveis);

            foreach($variaveis as $count => $variavel){
                array_push($indexes, [$variavel => $count+1]);
            }
        } else {
            //caso não encontre a variável de início da lista de variáveis
            foreach($variaveis as $count => $variavel){
                array_push($indexes, [$variavel => $count+1]);
            }
        }
        return $indexes;
        
    }
}
