<!DOCTYPE html>
<html>
    <head>
        <title>TP LFA - Ian Carlos e Muller Penaforte</title>
    </head>

    <body>
        <h1>TP LFA - Ian Carlos e Muller Penaforte</h1>
        <div id = 'descricao_ex'>
            
            <h3> Objetivo </h3>
            <p>O objetivo desse trabalho é permitir que os alunos apliquem os conceitos assimilados na disciplina em um trabalho prático de implementação. A ideia é desenvolver um dos algoritmos vistos na disciplina em um programa de computador.</p>
            <h3> Descrição </h3>
            <p>
            Considere uma gramática livre de contexto G = (V, Σ, R, P) para uma linguagem L sem regras λ, exceto quando λ ∈ L, sem regras unitárias e sem variáveis inúteis. Portanto, o conjunto de regras R desta gramática G segue o formato aseguir.
            </p>
            <h3> P → λ se λ ∈ L(G) </h3>
            <h3> X → a para a ∈ Σ </h3>
            <h3> X → w para |w| ≥ 2 </h3>

            <p>
            Transforme a gramática G, descrita acima, em uma GLC G' equivalente na forma normal de Greibach. O conjunto de regras R desta nova gramática G' segue o formato aseguir. 
            </p>
            <h3> P → λ se λ ∈ L(G) </h3>
            <h3> X → ay para a ∈ Σ e y ∈ V* </h3>
        </div>
        <div id = 'form'>
            <h3>Resolução</h3>
            <p>Anexe aqui o arquivo JSON.</p>
            <form method = 'POST' action = "{{route('processaArquivo')}}" enctype="multipart/form-data">
                @csrf
                <input id = "file" name = "file" type = "file">
                <button type = "submit">'Enviar arquivo' </button>
            </form>    
        </div>
        
    </body>

</html>