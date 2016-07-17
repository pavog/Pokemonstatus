<?php
/*
 * Main reporting code
 */

$m = new Memcached();
$m->addServer('127.0.0.1', 11211);

require __DIR__ . '/StatusChecker.class.php';

$PokemonStatus = new StatusChecker();

$Answer = Array('v' => 10, 'last_updated' => Date('H:i:s T'), 'report' => $PokemonStatus->GetReport());

unset($PokemonStatus);

$Raw = $Answer['report'];

foreach ($Raw as $Service => $Report) {
    $Downs = (int)$m->get('pokemon_status_' . $Service);

    if ($Report['status'] === StatusChecker :: STATUS_OFFLINE) {
        $Answer['report'][$Service]['downtime'] = '±' . ++$Downs;
    }
}

$m->set('pokemon_status', JSON_Encode($Answer), 1800);

$myfile = fopen("status.json", "w");
fwrite($myfile, JSON_Encode($Answer));

unset($Downs, $Report, $Service, $Answer);

/*
 * Downtime reporting
 */

$ProperNames = Array(
    'game'   => 'Game',
    'australia' => 'Australia',
    'germany' => 'Germany',
    'italy'   => 'Italy',
    'netherlands'  => 'Netherlands',
    'netherlands'  => 'Netherlands',
    'newzealand'  => 'New Zealand',
    'portugal'  => 'Portugal',
    'spain'  => 'Spain',
    'uk'  => 'United Kingdom',
    'us'  => 'United States',
    'other'  => 'Other Regions'
);

$ProperNameAdj = Array(
    'game'   => 'is',
    'australia' => 'is',
    'germany' => 'is',
    'italy'   => 'is',
    'netherlands'  => 'is',
    'netherlands'  => 'is',
    'newzealand'  => 'is',
    'portugal'  => 'is',
    'spain'  => 'is',
    'uk'  => 'is',
    'us'  => 'is',
    'other'  => 'are'
);

if (($BackOnline = $m->get('pokemon_status_back_online'))) {
    $m->delete('pokemon_status_back_online');

    foreach ($BackOnline as $Service => $Downs) {
        if ($Raw[$Service] === StatusChecker :: STATUS_OFFLINE) {
            $m->set('pokemon_status_' . $Service, ++$Downs, 120);
        } else {
            $Adj = $ProperNameAdj[$Service];

            //Tweet( ' ✔ ' . $ProperNames[ $Service ] . ' ' . $Adj . ' back online, ' . ( $Adj === 'is' ? 'it was' : 'they were' ) . ' down for ' . $Downs . ' minutes', Time( ) - 60 );
        }
    }
}

$BackOnline = Array();

foreach ($Raw as $Service => $Report) {
    if (!Array_Key_Exists($Service, $ProperNames)) {
        continue;
    }

    $Downs = (int)$m->get('pokemon_status_' . $Service);

    if ($Report['status'] === StatusChecker :: STATUS_OFFLINE) {
        $m->set('pokemon_status_' . $Service, ++$Downs, 120);

        if ($Downs == 20) {
            //Tweet( ' ✖ ' . $ProperNames[ $Service ] . ' ' . $ProperNameAdj[ $Service ] . ' down', Time( ) - 1200 );
        } else if ($Downs % 60 == 0) {
            //Tweet( ' ♦ ' . $ProperNames[ $Service ] . ' ' . $ProperNameAdj[ $Service ] . ' still down, ' . $Downs . ' minutes' );
        }
    } else if ($Downs > 0) {
        $m->delete('pokemon_status_' . $Service);

        if ($Downs >= 20) {
            $BackOnline[$Service] = $Downs;
        }
    }
}

if (!Empty($BackOnline)) {
    $m->set('pokemon_status_back_online', $BackOnline, 120);
}

function HandleNews($Data, $Code) {
    if ($Code !== 200) {
        return;
    }

    global $PSA, $m;

    $Data = JSON_Decode($Data, true);

    if ($Data === false || Empty($Data)) {
        $m->set('pokemon_status_mojang', '', 300);

        return;
    }

    $PSA = '';

    foreach ($Data as $Message) {
        if ($Message['game'] !== 'Pokémon') {
            continue;
        }

        if (!Empty($PSA)) {
            $PSA .= '<hr class="dotted">';
        }

        $PSA .= '<h3 style="margin-top:0">' . HTMLEntities($Message['headline']) . ' <span class="muted" style="font-weight:400">(from <a href="http://http://pokemongo.nianticlabs.com//">http://pokemongo.nianticlabs.com/</a>)</span></h3>' . $Message['message'];
    }

    $m->set('pokemon_status_mojang', $PSA, 300);
}
