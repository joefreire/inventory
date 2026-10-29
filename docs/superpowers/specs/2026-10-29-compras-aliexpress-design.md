# Compras do AliExpress no inventário

Data: 2026-10-29. Status: design para aprovação.

## Por que

Ao cadastrar uma peça, a IA hoje só tem a foto tirada na bancada e às vezes erra o
nome e as specs. Quase tudo que o dono compra vem do AliExpress ou do Mercado Livre,
e a compra é a fonte certa: nome, foto oficial, quantidade, preço, data e onde
recomprar.

Duas situações reais, as duas importam:

1. **Pacote que acabou de chegar.** O dono sabe de qual pedido veio e quer cadastrar
   a partir dele, sem digitar.
2. **Peça solta, origem esquecida.** O dono tira a foto e quer que o app sugira de
   qual compra ela veio.

## Decisões do dono

- **Primeiro só AliExpress.** O Mercado Livre fica para depois: os e-mails dele
  estão em outra conta, ainda não resolvida.
- **Fonte: os e-mails do AliExpress no Gmail `freire.joe@gmail.com`.** Nada de Chrome
  logado e nada de API das lojas.
- **O sistema não acessa o Gmail.** Quem lê os e-mails é uma **skill do Claude Code**
  (`/importar-pedidos`), rodada **manualmente** pelo dono. Ela gera um arquivo e o
  entrega ao inventário.
- **Área separada "Compras" no inventário**, com uma tela para **importar pedidos**.
- **O nome completo vem da IA.** O e-mail traz o nome cortado. A IA completa o nome a
  partir da foto oficial mais o nome cortado. O nome cortado original fica guardado, e
  o completado fica marcado como "completado pela IA". Pegar o nome exato do site
  fica para depois, se fizer falta.
- **O histórico é ligado às peças existentes pela IA, com confirmação em lote.** Depois
  da primeira importação, a IA propõe "este item de compra é esta peça do inventário" e
  o dono confirma ou recusa vários de uma vez. Sem isso, o filtro "entregue e ainda não
  cadastrado" viraria centenas de itens que já estão guardados.
- **Sem link do anúncio.** O e-mail não traz o anúncio do item comprado. O link do
  **pedido** (abre no app do AliExpress, onde tem "comprar de novo") faz esse papel.

## O que os e-mails trazem (verificado em 2026-10-28)

Remetente `transaction@notice.aliexpress.com`, cerca de 1.440 e-mails no último ano.
Os assuntos seguem padrões fixos:

- `Pedido <n>: pedido confirmado`, `aguardando confirmação`, `pedido enviado`,
  `coletado pela transportadora`, `como foi a experiência?`
- `Pacote <rastreio>: ...`, `O pacote <rastreio> foi entregue`,
  `Atualização sobre N pacote(s)`

Os e-mails de **pedido** trazem, no HTML:

- número do pedido (`orderId`) e link `https://www.aliexpress.com/p/order/detail.html?orderId=<n>`;
- por item: nome **cortado** (ex.: `<span>SONOFF ZBMINIR2 Interruptor I...</span>`),
  variação (`ZBMINIR2 1 PC`), quantidade (`x1`);
- total do pedido (`R$56,79`);
- a **foto oficial do anúncio** em `ae-pic-a1.aliexpress-media.com/kf/<id>.jpg`, que na
  prática é WebP de 1600×1600 (conferido com o SONOFF ZBMINIR2);
- endereço de entrega e telefone do dono, que **não podem** ser copiados para o JSON;
- um bloco "Você também vai gostar" com `productId=` e imagens de **outros** produtos.
  Isso é propaganda e não pode virar item.

Não trazem: o link do anúncio comprado, o nome completo, as specs.

## Partes

### 1. Skill `/importar-pedidos` (no homelab)

Vive em `homelab/.claude/skills/importar-pedidos/` (o diretório onde já está
`check-casa`).

- Lê os e-mails do AliExpress pelo Gmail MCP do Claude Code.
- Lê só o que falta: o inventário informa o que já tem (ver "Contrato").
- Monta o JSON (formato abaixo) e o entrega ao inventário. O jeito de entregar é
  escolha do construtor. O padrão sugerido é SSH no canivete, como
  `casa-prea/scripts/inventario.sh` já faz (o site exige login).
- Nunca leva endereço, telefone, nome de destinatário nem os produtos de propaganda.
- Termina dizendo quantos pedidos e itens entraram, quantos foram atualizados e quais
  e-mails não conseguiu ler.
- A primeira execução traz o histórico inteiro (todos os e-mails do AliExpress, não
  só do último ano).

### 2. Área "Compras" no inventário

**Dados:** pedido (loja, número, data, status, total, link do pedido) e item do pedido
(nome cortado, nome completado pela IA, variação, quantidade, preço quando houver,
foto oficial, specs, classificação peça/ferramenta ou outro, peça do inventário
vinculada).

Uma peça pode ter **várias** compras (recompra é comum); cada item de compra aponta
para no máximo uma peça. Um item também pode ser **dispensado** ("não guardo"), e aí
sai das pendências sem virar peça.

Pedido cancelado ou reembolsado entra com esse status e nunca conta como pendente
(*decisão do Claude*).

**Importar pedidos:** uma tela recebe o JSON por upload. A skill usa o mesmo caminho
por outro meio. Importar de novo o mesmo pedido **atualiza**, sem duplicar
(identidade: loja + número do pedido, e item dentro do pedido). O status anda para a
frente: confirmado → enviado → entregue.

**Enriquecimento pela IA, depois de importar:**

- Usa o Ollama Cloud que o app já usa (regra do dono: IA de projeto pessoal é sempre
  Ollama Cloud).
- Para cada item novo, recebe a foto oficial mais o nome cortado e devolve o nome
  completo, as specs e se é peça/ferramenta ou outro.
- A foto oficial é baixada e guardada no disco privado `media`. Ela não pode depender
  do link do AliExpress continuar no ar.
- São centenas de itens na primeira vez. Não pode travar a tela de importação: roda
  em segundo plano. Em produção **não existe worker de fila do inventário**
  (`QUEUE_CONNECTION=database`, mas nenhum serviço processa a fila; o
  `canivete-queue.service` é de outro app). Fazer funcionar sem fila, ou criar o
  worker, fica com o construtor.
- Se a IA falhar num item, ele fica com o nome cortado e marcado "sem enriquecimento",
  para tentar de novo depois. Não pode virar sucesso silencioso.

**Lista de compras:**

- Pedidos e itens, mais novos primeiro.
- Por padrão mostra só os itens peça/ferramenta. Dá para ver todos.
- Filtro **"entregue e ainda não cadastrado"**: a lista do que falta guardar.

**Do item da compra para a peça:**

- **Pacote novo:** o botão "Cadastrar peça" no item abre o cadastro normal já preenchido
  com o nome completo, as specs, a quantidade e a foto oficial. O dono escolhe a caixa
  e pode trocar ou acrescentar fotos. Ao salvar, o item da compra fica vinculado à peça.
- **Recompra:** no item, "Juntar a uma peça que já tenho" liga o item a uma peça
  existente e soma a quantidade, com confirmação mostrando o número a somar
  (*decisão do Claude*, a partir da escolha do dono pela ligação com peças existentes).
- **Quantidade:** quantidade do pedido × unidades do pacote. A IA lê o pacote da
  variação ("100 PCS" = 100, "5 pares" = 10), como já faz com a embalagem na foto. Vem
  preenchida e o dono corrige (*decisão do Claude*).
- **Ligação do histórico em lote:** uma tela lista as propostas da IA ("item de compra
  → peça do inventário", com as duas fotos lado a lado) para confirmar ou recusar várias
  de uma vez. Confirmar no lote **não** soma quantidade: a peça já foi contada quando
  foi cadastrada (*decisão do Claude*). Itens sem proposta ficam pendentes, para
  cadastrar, juntar ou dispensar.
- **Peça solta:** no cadastro normal, depois da análise da foto, o app também sugere
  "parece o item X do pedido Y (data)", quando houver item de compra parecido ainda sem
  peça. O dono confirma ou ignora. Pode ser uma pergunta nova no padrão
  `PartDecision`, como o "Já tenho isso?".
- **Na página da peça:** a lista das compras dela ("AliExpress, <data>, <preço>, pedido
  <n>"), cada uma com o link do pedido.

## Contrato skill ↔ inventário

O construtor define o formato exato. O mínimo:

```json
{
  "source": "aliexpress",
  "orders": [
    {
      "number": "8215761587024729",
      "ordered_at": "2026-10-20",
      "status": "shipped",
      "total": "56.79",
      "currency": "BRL",
      "url": "https://www.aliexpress.com/p/order/detail.html?orderId=8215761587024729",
      "items": [
        {
          "title_truncated": "SONOFF ZBMINIR2 Interruptor I...",
          "variant": "ZBMINIR2 1 PC",
          "quantity": 1,
          "image_url": "https://ae-pic-a1.aliexpress-media.com/kf/Sd0743a5e8fb24bee96a9859e995a85c26.jpg"
        }
      ]
    }
  ]
}
```

- Os links do JSON vão **sem** os parâmetros de rastreamento (`tracelog`, `rowan_*`,
  `mem_info`, `edm_*`).
- Para a skill saber o que já foi importado, o inventário oferece a lista de pedidos
  que já tem, com o status.

## Fora do escopo

- Mercado Livre (vem depois, com o mesmo formato e `source: "mercadolivre"`).
- O app acessar o Gmail, o Chrome ou o site das lojas.
- Execução agendada da skill.
- Link do anúncio e nome exato do site.
- Controle financeiro (somas, gráficos de gasto).

## Antiobjetivos (falhou mesmo funcionando, se...)

- Endereço ou telefone do dono aparecem no banco, no JSON ou nos logs.
- Produtos de propaganda viram itens de compra.
- Importar duas vezes duplica pedido ou item.
- A ligação do histórico em lote muda a quantidade de uma peça.
- A IA falha e o item aparece como enriquecido.
- A foto da compra some porque o AliExpress tirou do ar.

## Fica com o construtor

- Nomes de tabelas, rotas e telas, seguindo `docs/ARCHITECTURE.md`.
- Como a skill entrega o JSON ao canivete.
- Fila ou processamento em lotes do enriquecimento.
- Como reconhecer o status de cada assunto de e-mail, e como ligar e-mails de pacote
  (`Pacote NN...BR`) ao pedido para saber se foi entregue.
- Os testes (o padrão do projeto: `composer check`, IA com `Http::fake`, E2E com Playwright).
