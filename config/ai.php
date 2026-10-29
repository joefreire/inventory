<?php

use App\Ai\Decisions\CategoryDecision;
use App\Ai\Decisions\DuplicateDecision;
use App\Ai\Decisions\LocationDecision;
use App\Ai\Decisions\TagsDecision;

/*
 * IA do inventario: Ollama Cloud com dois tipos de modelo.
 *
 * - vision: modelos de chat que leem a foto e escrevem nome, descricao e
 *   specs. Lista em ordem de preferencia; se um falhar (erro, 429, JSON
 *   invalido), tenta o proximo.
 * - decision: modelo de decisao (/v1/systemone). Nao escreve texto: devolve
 *   probabilidades para perguntas fechadas (categoria, tags, caixa,
 *   duplicado, busca).
 */
return [
    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'https://ollama.com'),
        'api_key' => env('OLLAMA_API_KEY'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 90),
    ],

    'models' => [
        'vision' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('AI_VISION_MODELS', 'gemma4:31b,kimi-k3,glm-5.3-flash'))
        ))),
        'decision' => env('AI_DECISION_MODEL', 'clef'),
    ],

    // Limites do /v1/systemone na nuvem.
    'decision_limits' => [
        'questions' => 64,
        'choice_options' => 255,
        'images' => 4,
    ],

    // Probabilidade minima para a decisao ser usada sem perguntar.
    'thresholds' => [
        'tag' => 0.7,
        'duplicate' => 0.6,
        'location' => 0.4,
        'search' => 0.5,
    ],

    // Categorias fixas: o modelo de decisao escolhe uma delas.
    'categories' => [
        'resistor' => 'Resistor',
        'capacitor' => 'Capacitor',
        'indutor' => 'Indutor ou bobina',
        'diodo' => 'Diodo (inclui zener e ponte retificadora)',
        'led' => 'LED, fita de LED ou display de LED simples',
        'transistor' => 'Transistor, MOSFET ou regulador de tensão avulso',
        'ic' => 'Circuito integrado (chip avulso)',
        'microcontrolador' => 'Placa de microcontrolador (ESP32, ESP8266, Arduino, RP2040)',
        'modulo' => 'Módulo ou placa pronta (driver, conversor, relé, rádio, teclado)',
        'sensor' => 'Sensor (temperatura, presença, distância, corrente, gás)',
        'display' => 'Tela ou display (LCD, OLED, e-paper, TFT)',
        'audio' => 'Som (buzzer, alto-falante, microfone, amplificador)',
        'conector' => 'Conector, borne, header ou jumper',
        'cabo' => 'Cabo ou fio',
        'fonte' => 'Fonte, carregador, bateria, UPS ou conversor de energia',
        'protecao' => 'Proteção (fusível, porta-fusível, varistor, disjuntor)',
        'motor' => 'Motor, servo, solenoide, trava elétrica ou bomba',
        'rele' => 'Relé avulso ou contator',
        'chave' => 'Chave, botão ou interruptor',
        'mecanica' => 'Peça mecânica (rolamento, polia, correia, engrenagem, eixo, mola)',
        'fixacao' => 'Parafuso, porca, inserto, espaçador ou fixação',
        'impressao_3d' => 'Peça impressa em 3D ou filamento',
        'ferramenta' => 'Ferramenta',
        'outro' => 'Outra coisa',
    ],

    // Tags que servem para quase tudo e por isso nao filtram nada (revisao de
    // 2026-10-25: "componente" em 15 de 23 pecas, "eletrica" em 14).
    // Comparadas sem acento e sem hifen.
    'generic_tags' => [
        'componente', 'componentes', 'eletrica', 'eletrico', 'eletronica', 'eletronico',
        'eletronicos', 'hardware', 'peca', 'pecas', 'item', 'diy', 'maker', 'projeto',
        'projetos', 'geral', 'outro', 'outros', 'modulo', 'modulos', 'dc', 'placa', 'controle',
    ],

    // No maximo quantas tags a IA sugere por peca (existentes + novas).
    'max_tags' => 5,

    // Perguntas feitas ao modelo de decisao em cada analise de peca, numa
    // chamada so. Pergunta nova = classe que implementa
    // App\Ai\Decisions\PartDecision, listada aqui.
    'decisions' => [
        CategoryDecision::class,
        TagsDecision::class,
        LocationDecision::class,
        DuplicateDecision::class,
    ],

    // Busca em linguagem natural: quantos itens no maximo sao avaliados (os
    // mudados mais recentemente) e por quanto tempo a mesma pergunta fica em cache.
    'search' => [
        'max_items' => (int) env('AI_SEARCH_MAX_ITEMS', 500),
        'cache_minutes' => 10,
    ],

    // Unicos hosts de onde a foto oficial de uma compra pode ser baixada
    // (dominio ou subdominio, so HTTPS).
    'purchase_image_hosts' => ['aliexpress-media.com', 'alicdn.com'],

    // Limite de chamadas a IA por usuario por minuto (custo e abuso).
    'rate_limit_per_minute' => (int) env('AI_RATE_LIMIT', 20),
];
