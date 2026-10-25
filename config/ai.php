<?php

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
        'transistor' => 'Transistor, MOSFET ou regulador de tensao em encapsulamento TO',
        'ic' => 'Circuito integrado (chip avulso)',
        'microcontrolador' => 'Placa de microcontrolador (ESP32, ESP8266, Arduino, RP2040)',
        'modulo' => 'Modulo ou placa pronta (driver, conversor, rele, display, radio)',
        'sensor' => 'Sensor (temperatura, presenca, distancia, corrente, gas)',
        'display' => 'Tela ou display (LCD, OLED, e-paper, TFT)',
        'conector' => 'Conector, borne, header ou jumper',
        'cabo' => 'Cabo ou fio',
        'fonte' => 'Fonte, carregador, bateria ou conversor de energia',
        'motor' => 'Motor, servo, atuador ou bomba',
        'rele' => 'Rele avulso ou contator',
        'chave' => 'Chave, botao ou interruptor',
        'ferramenta' => 'Ferramenta',
        'impressao_3d' => 'Peca impressa em 3D ou filamento',
        'fixacao' => 'Parafuso, porca, espacador ou fixacao',
        'outro' => 'Outra coisa',
    ],
];
