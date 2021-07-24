<?php

return [
    'secret'       => 'super_secret',
    'exe' => __DIR__ . '/exe.php',

    'repeat' => 3600,
    'delete_time' => 60,
    'silence_time' => '300',

    'allowed_chats' => [
        // ids
    ],

    'increase' => [
        'dig' => 100,
        'hunt' => 75,
        'fish' => 50,
    ],

    'targets' => [
        'dig' => [
            // names
        ],
        'hunt' => [
            // names
        ],
        'fish' => [
            // names
        ],
    ],

    'places' => [
        'dig' => [
            // names
        ],
        'hunt' => [
            // names
        ],
        'fish' => [
            // names
        ]
    ],

    'fail' => [
        'dig' => [
            // phrases with {{place}} and {{nick}} placeholders
        ],
        'hunt' => [
            // phrases
        ],
        'fish' => [
            // phrases
        ],
    ],

    'trophy' => [
        // phrases with {{nick}}, {{target}}, {{size}} placeholders
        'dig' => "",
        'hunt' => "",
        'fish' => "",
    ],

    'wait' => [
        // phrases with {{time}} placeholder
        'dig' => "",
        'hunt' => "",
        'fish' => "",
    ],

    'action' => [
        // phrases with {{place}} placeholder
        'dig' => "",
        'hunt' => "!",
        'fish' => "",
    ],

    'success' => [
        // phrases with {{nick}}, {{target}}, {{size}} placeholders
        'dig' => "\n",
        'hunt' => "\n",
        'fish' => "\n",
    ],

    'record' => [
        // phrases with {{nick}} placeholders
        'dig' => "",
        'hunt' => "",
        'fish' => "",
    ],

    'not_record' => [
        // phrases with {{nick}} placeholders
        'dig' => "",
        'hunt' => "",
        'fish' => "",
    ],

    'command_chars' => ['/', '!', 'ǃ'],

    'commands' => [
        'dig' => [
            'копать',
            'копати',
            'dig',
            'копання',
            'копание',
        ],
        'hunt' => [
            'hunt',
            'охота',
            'полювання',
            'полювати',
            'охотиться',
        ],
        'fish' => [
            'cast',
            'fishing',
            'рыбалка',
            'рибалка',
            'риболовити',
            'рибачити',
            'рыбачить',
            'риболовля',
        ],
        'trophy' => [
            'trophies',
            'trophy',
            'трофеи',
            'трофеї',
        ],
    ],
];
