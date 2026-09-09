# Checklist assistido — criação de campos nos documentos do Project+

## Objetivo

Validar pelo navegador o fluxo completo de criação, edição, exibição e preenchimento de novos campos nos documentos do Project+:

- TIP — Termo de Intenção de Projeto
- TIP-DT — Detalhamento Técnico
- TAP — Termo de Abertura do Projeto
- TEP
- TA

Use a tela de criação de questões do **Formcreator somente como referência visual e comportamental**, especialmente para:

- organização e fluidez do formulário;
- campos complementares exibidos de acordo com o tipo selecionado;
- configuração das condições de exibição;
- comportamento do tipo “Objeto do GLPI”.

O Project+ deve funcionar de forma independente. A validação não deve exigir que um campo ou questão do Formcreator seja relacionado ao Project+.

## Restrições do teste

- Não alterar código-fonte, arquivos do plugin ou banco de dados.
- Não executar comandos no servidor.
- Não instalar, atualizar ou remover plugins.
- Realizar todas as operações exclusivamente pela interface do GLPI.
- Não corrigir defeitos encontrados. Registrar evidências e continuar quando for seguro.
- Não usar dados reais ou sensíveis nos campos de teste.
- Utilizar nomes iniciados por `TESTE_CLAUDE_` para facilitar a identificação.
- Antes de excluir configurações criadas, registrar os resultados e solicitar autorização ao responsável pelo teste assistido.

## Preparação

- [ ] Confirmar que a sessão está autenticada como **Super-Admin**.
- [ ] Confirmar que o perfil Super-Admin consegue acessar todas as configurações do Project+.
- [ ] Anotar a versão exibida do Project+.
- [ ] Anotar a entidade ativa e se entidades filhas estão habilitadas.
- [ ] Abrir o Formcreator e observar a tela **Adicionar uma questão**.
- [ ] No Formcreator, alternar entre um tipo comum e “Objeto do GLPI”.
- [ ] Confirmar que o seletor adicional “Objeto do GLPI” aparece somente quando esse tipo é escolhido.
- [ ] No Formcreator, observar a área “Condições de exibição da questão”.
- [ ] Não salvar a questão de referência no Formcreator.
- [ ] Abrir em outra aba as configurações do Project+.

## Parte A — validação da interface de criação

Executar inicialmente no TIP e depois repetir a verificação essencial no TIP-DT, TAP, TEP e TA.

- [ ] Acessar a aba do documento.
- [ ] Clicar em **Adicionar campo**.
- [ ] Confirmar que o formulário possui apresentação compacta e coerente com o padrão visual do GLPI.
- [ ] Confirmar que aparecem: Campo/Rótulo, Tipo, Descrição/orientação, origem, ordem, obrigatório, ativo e condições de exibição.
- [ ] Confirmar que “Objeto do GLPI” está oculto quando o tipo inicial é “Texto curto”.
- [ ] Selecionar “Objeto do GLPI”.
- [ ] Confirmar que o seletor complementar “Objeto do GLPI” aparece imediatamente.
- [ ] Selecionar novamente “Texto curto”.
- [ ] Confirmar que o seletor complementar desaparece imediatamente.
- [ ] Confirmar que o tipo “Usuário/ator” não aparece na lista.
- [ ] Confirmar que a seção de condições inicia em “Sempre exibido”.
- [ ] Confirmar que os detalhes da condição ficam ocultos em “Sempre exibido”.
- [ ] Selecionar “Exibir conforme resposta”.
- [ ] Confirmar que aparecem Campo de referência, Operador e Valor.
- [ ] Selecionar “Está vazio” e “Está preenchido”.
- [ ] Confirmar que o campo Valor fica oculto nesses dois operadores.
- [ ] Retornar ao operador “É igual a”.
- [ ] Confirmar que o campo Valor reaparece.

## Parte B — tipos de campo comuns

Crie campos de teste no TIP com ordem crescente. Para tipos de escolha, informe pelo menos três opções: `Opção A`, `Opção B` e `Opção C`.

| Nº | Nome sugerido | Tipo | Configuração mínima | Resultado esperado |
|---:|---|---|---|---|
| 1 | TESTE_CLAUDE_TEXTO_CURTO | Texto curto | Opcional e ativo | Caixa de uma linha |
| 2 | TESTE_CLAUDE_TEXTO_LONGO | Texto longo | Opcional e ativo | Área de texto multilinha |
| 3 | TESTE_CLAUDE_INTEIRO | Número inteiro | Opcional e ativo | Aceita inteiro |
| 4 | TESTE_CLAUDE_DECIMAL | Número decimal | Opcional e ativo | Aceita número decimal |
| 5 | TESTE_CLAUDE_EMAIL | E-mail | Opcional e ativo | Valida formato de e-mail |
| 6 | TESTE_CLAUDE_DATA | Data | Opcional e ativo | Controle de data |
| 7 | TESTE_CLAUDE_DATA_HORA | Data e hora | Opcional e ativo | Controle de data e hora |
| 8 | TESTE_CLAUDE_HORA | Hora | Opcional e ativo | Controle de horário |
| 9 | TESTE_CLAUDE_LISTA | Lista suspensa | Três opções | Seleção de uma opção |
| 10 | TESTE_CLAUDE_UNICA | Seleção única | Três opções | Opções exclusivas |
| 11 | TESTE_CLAUDE_CHECK | Caixas de seleção | Três opções | Permite marcar várias |
| 12 | TESTE_CLAUDE_MULTIPLA | Seleção múltipla | Três opções | Permite selecionar várias |
| 13 | TESTE_CLAUDE_SECAO | Título de seção | Ativo | Divide visualmente o formulário |
| 14 | TESTE_CLAUDE_INFO | Texto informativo | Orientação preenchida | Exibe informação sem resposta |

Para cada campo:

- [ ] Salvar pela interface.
- [ ] Confirmar mensagem de sucesso.
- [ ] Confirmar retorno à lista correta do documento.
- [ ] Confirmar que o campo aparece somente na aba do documento em que foi criado.
- [ ] Confirmar rótulo, tipo, ordem, obrigatoriedade e situação.
- [ ] Abrir **Editar** e conferir se os valores persistiram.
- [ ] Salvar novamente sem alteração e confirmar ausência de erro.

Validações negativas:

- [ ] Tentar salvar um campo sem nome e confirmar bloqueio.
- [ ] Tentar salvar um tipo de escolha sem opções e confirmar bloqueio.
- [ ] Confirmar que Título de seção e Texto informativo não podem se tornar obrigatórios.
- [ ] Confirmar que Título de seção e Texto informativo não possuem preenchimento de origem efetivo.

## Parte C — Objeto do GLPI

Criar campos `TESTE_CLAUDE_OBJETO_*` e testar, no mínimo:

- [ ] Entidade.
- [ ] Categoria ITIL.
- [ ] Localização.
- [ ] Grupo.
- [ ] Usuário.
- [ ] Fornecedor.
- [ ] Projeto.
- [ ] Chamado.

Para cada objeto:

- [ ] Selecionar o tipo “Objeto do GLPI”.
- [ ] Confirmar que o seletor complementar aparece.
- [ ] Selecionar o objeto desejado e salvar.
- [ ] Editar o campo e confirmar que o objeto escolhido permaneceu salvo.
- [ ] Abrir o documento para preenchimento.
- [ ] Confirmar que é exibida a pesquisa/lista nativa correspondente ao objeto.
- [ ] Confirmar que a pesquisa respeita entidade e permissões da sessão.
- [ ] Selecionar um registro válido, salvar o documento e reabrir.
- [ ] Confirmar que o registro selecionado permanece preenchido.
- [ ] Confirmar que não são mostrados registros inacessíveis ao contexto atual.

## Parte D — condições de exibição

Crie no mesmo documento:

1. `TESTE_CLAUDE_GATILHO`, tipo Lista suspensa, com opções `Sim`, `Não` e `Talvez`.
2. `TESTE_CLAUDE_CONDICIONAL`, tipo Texto curto.

Configure o segundo campo como “Exibir conforme resposta”, referenciando o primeiro.

### Operador “É igual a”

- [ ] Configurar valor `Sim`.
- [ ] Confirmar que o campo condicional inicia oculto quando o gatilho está vazio.
- [ ] Selecionar `Não` e confirmar que continua oculto.
- [ ] Selecionar `Sim` e confirmar que aparece imediatamente.
- [ ] Voltar para `Não` e confirmar que desaparece.

### Demais operadores

- [ ] “É diferente de”: validar resultado verdadeiro e falso.
- [ ] “Contém”: validar correspondência parcial e ausência de correspondência.
- [ ] “Está vazio”: validar com gatilho vazio e preenchido.
- [ ] “Está preenchido”: validar com gatilho vazio e preenchido.

### Obrigatoriedade condicional

- [ ] Tornar `TESTE_CLAUDE_CONDICIONAL` obrigatório.
- [ ] Manter a condição falsa e salvar/enviar o documento.
- [ ] Confirmar que o campo oculto não impede o envio.
- [ ] Tornar a condição verdadeira e tentar enviar sem preenchê-lo.
- [ ] Confirmar bloqueio por obrigatoriedade.
- [ ] Preencher o campo e confirmar que o envio funciona.
- [ ] Confirmar, ao reabrir, que apenas respostas de campos efetivamente exibidos foram gravadas.

### Integridade da configuração

- [ ] Confirmar que um campo não pode usar a si próprio como referência.
- [ ] Confirmar que só aparecem campos do mesmo documento e da mesma entidade.
- [ ] Tentar salvar condição sem Campo de referência e confirmar rejeição.
- [ ] Confirmar que campos de outro documento não aparecem como referência.

## Parte E — repetição nos cinco documentos

Após a bateria completa no TIP, execute em cada documento restante um teste reduzido:

| Documento | Texto curto | Lista | Objeto GLPI | Condicional | Preencher e persistir |
|---|---:|---:|---:|---:|---:|
| TIP | [ ] | [ ] | [ ] | [ ] | [ ] |
| TIP-DT | [ ] | [ ] | [ ] | [ ] | [ ] |
| TAP | [ ] | [ ] | [ ] | [ ] | [ ] |
| TEP | [ ] | [ ] | [ ] | [ ] | [ ] |
| TA | [ ] | [ ] | [ ] | [ ] | [ ] |

Em cada documento:

- [ ] Criar um texto curto.
- [ ] Criar uma lista com três opções.
- [ ] Criar um Objeto do GLPI.
- [ ] Criar um campo condicionado à lista.
- [ ] Percorrer o fluxo até a etapa em que o documento possa ser preenchido.
- [ ] Preencher, salvar e reabrir.
- [ ] Confirmar persistência dos valores.
- [ ] Confirmar que os campos não aparecem em outro modelo documental.

## Parte F — edição, desativação e compatibilidade

- [ ] Editar o rótulo de um campo ainda não utilizado e confirmar a alteração.
- [ ] Alterar sua ordem e confirmar a nova posição.
- [ ] Desativar um campo e confirmar que ele deixa de aparecer para preenchimento.
- [ ] Reativar o campo e confirmar que volta a aparecer.
- [ ] Confirmar que os campos antigos, quando existirem, continuam como “Sempre exibido”.
- [ ] Confirmar que documentos históricos continuam abrindo sem erro.
- [ ] Confirmar que respostas históricas não foram apagadas ou modificadas.
- [ ] Confirmar que ações em massa continuam disponíveis na lista de campos.
- [ ] Confirmar que marcar/desmarcar obrigatoriedade em massa continua funcionando.

## Parte G — critérios de reprovação imediata

Registrar como defeito crítico se ocorrer qualquer situação abaixo:

- tela branca ou erro PHP;
- perda de respostas históricas;
- campo de um documento aparecendo em outro;
- campo oculto impedindo envio por obrigatoriedade;
- condição permitindo ignorar campo obrigatório quando visível;
- objeto GLPI exibindo ou aceitando registro sem permissão;
- seleção alterada após salvar e reabrir;
- dependência funcional do Formcreator;
- Super-Admin impedido de criar, editar ou testar campos.

## Registro obrigatório de evidências

Para cada falha, registrar:

```text
ID do teste:
Documento:
Entidade:
Perfil/usuário:
URL:
Campo e tipo:
Condição configurada:
Passos executados:
Resultado esperado:
Resultado obtido:
Mensagem de erro:
Horário:
Evidência (captura de tela):
Severidade: bloqueante | alta | média | baixa
```

## Relatório final esperado do Claude

Entregar o resultado dividido em:

1. **Resumo executivo** — aprovado, aprovado com ressalvas ou reprovado.
2. **Cobertura** — quantidade de itens executados, aprovados, reprovados e não executados.
3. **Resultado por documento** — TIP, TIP-DT, TAP, TEP e TA.
4. **Resultado por tipo de campo**.
5. **Resultado das condições de exibição**.
6. **Defeitos encontrados**, ordenados por severidade.
7. **Evidências** com URL e captura de tela.
8. **Itens não testados** e respectiva justificativa.
9. **Recomendação** sobre prosseguir ou não para a próxima release.

Durante esta execução, o Claude deve atuar somente como testador funcional assistido. Não deve alterar código para corrigir resultados.
