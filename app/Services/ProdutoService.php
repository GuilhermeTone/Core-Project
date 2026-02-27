<?php

namespace App\Services;

use App\Models\Produto;

class ProdutoService
{
    
    public function adicionaProduto($nome, $valor, $tipo): Produto            
    {
        return Produto::create(attributes:  [
            'nome' => $nome,
            'valor' => $valor,
            'tipo' => $tipo
        ]);
    }
    public function adicionaValorDeAcordoComTipo($valor, $tipo){

        if($tipo == "eletronico"){
            return $valor * 1.2;
        } else if($tipo == "roupa"){
            return $valor * 1.1;
        } else {
            return $valor;
        }

    }
    
}   

