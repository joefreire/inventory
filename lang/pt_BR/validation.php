<?php

// So as regras que o app usa. O resto cai no ingles do Laravel.
return [
    'required' => 'Preencha :attribute.',
    'required_without' => 'Preencha :attribute ou :values.',
    'string' => ':Attribute precisa ser texto.',
    'integer' => ':Attribute precisa ser um número inteiro.',
    'boolean' => ':Attribute precisa ser sim ou não.',
    'json' => ':Attribute veio num formato inválido.',
    'image' => ':Attribute precisa ser uma imagem.',
    'exists' => ':Attribute não existe mais.',
    'unique' => 'Ja existe :attribute com esse nome.',
    'regex' => ':Attribute esta num formato inválido.',
    'not_in' => ':Attribute não pode ser esse valor.',
    'between' => ['numeric' => ':Attribute precisa estar entre :min e :max.'],
    'min' => ['numeric' => ':Attribute precisa ser pelo menos :min.', 'string' => ':Attribute precisa ter pelo menos :min caracteres.'],
    'max' => ['numeric' => ':Attribute pode ser no máximo :max.', 'string' => ':Attribute pode ter no máximo :max caracteres.', 'file' => ':Attribute pode ter no máximo :max KB.'],

    'attributes' => [
        'name' => 'o nome',
        'quantity' => 'a quantidade',
        'location_id' => 'a caixa',
        'parent_id' => 'a caixa de fora',
        'photo' => 'a foto',
        'color' => 'a cor',
        'tags' => 'as tags',
        'email' => 'o e-mail',
        'form.email' => 'o e-mail',
        'form.password' => 'a senha',
        'delta' => 'a quantidade',
        'tag' => 'a tag',
    ],
];
