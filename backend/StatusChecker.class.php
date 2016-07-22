<?php

class StatusChecker {
    // Useragent
    const USER_AGENT = 'Pokémon GO Status Checker (https://github.com/pavog/pokemonstatus)';

    // Statuses as string
    const STATUS_OFFLINE = 'down';
    const STATUS_ONLINE = 'up';
    const STATUS_PERF_DEGRADATION = 'problem';

    private $Report = Array();

    public function __construct() {
        $Checks = Array(Array('Name' => 'game', 'Callback' => 'CheckGame', 'Timeout' => 4, 'URL' => '130.211.14.80'), Array('Name' => 'auth0', 'Callback' => 'CheckAuth0', 'Timeout' => 4, 'URL' => 'sso.pokemon.com'), Array('Name' => 'auth1', 'Callback' => 'CheckAuth1', 'Timeout' => 4, 'URL' => '174.35.46.81'), Array('Name' => 'auth2', 'Callback' => 'CheckAuth2', 'Timeout' => 4, 'URL' => '174.35.46.81'), Array('Name' => 'con1', 'Callback' => 'CheckCon1', 'Timeout' => 4, 'URL' => '54.241.32.23'), Array('Name' => 'con2', 'Callback' => 'CheckCon2', 'Timeout' => 4, 'URL' => 'pgorelease.nianticlabs.com'), //Array('Name' => 'australia', 'Callback' => 'CheckAustralia', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            Array('Name' => 'germany', 'Callback' => 'CheckGermany', 'Timeout' => 4, 'URL' => '119.81.248.44'), //Array('Name' => 'italy', 'Callback' => 'CheckItaly', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            //Array('Name' => 'netherlands', 'Callback' => 'CheckNetherlands', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            //Array('Name' => 'newzealand', 'Callback' => 'CheckNewzealand', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            //Array('Name' => 'portugal', 'Callback' => 'CheckPortugal', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            //Array('Name' => 'spain', 'Callback' => 'CheckSpain', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            Array('Name' => 'uk', 'Callback' => 'CheckUK', 'Timeout' => 4, 'URL' => '37.58.73.184'), Array('Name' => 'us', 'Callback' => 'CheckUS', 'Timeout' => 4, 'URL' => '74.125.136.95'), Array('Name' => 'na', 'Callback' => 'CheckNA', 'Timeout' => 4, 'URL' => '130.211.188.132'),//Array('Name' => 'other', 'Callback' => 'CheckOther', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe')
        );

        $Requests = Array();
        $Master = cURL_Multi_Init();

        foreach ($Checks as $Check) {
            $Slave = $this->CreateSlave($Check['URL'], $Check['Timeout']);

            /*
            if( $Check[ 'Name' ] === 'game' )
            {
                cURL_SetOpt_Array( $Slave, Array(
                    CURLOPT_POST       => true,
                    CURLOPT_POSTFIELDS => '{"agent":"Minecraft","clientToken":"","username":"this-used-to-be-a-real-username","password":"this-used-to-be-a-real-password"}',
                    CURLOPT_HTTPHEADER => Array( 'Content-Type: application/json' )
                ) );
            }
            */

            cURL_Multi_Add_Handle($Master, $Slave);

            $Requests[(int)$Slave] = Array('Name' => $Check['Name'], 'Callback' => $Check['Callback']);
        }

        unset($Checks);

        echo 'Doing a thing' . PHP_EOL;

        $Running = true;

        do {
            while (($Exec = cURL_Multi_Exec($Master, $Running)) === CURLM_CALL_MULTI_PERFORM) ;

            if ($Exec !== CURLM_OK) {
                break;
            }

            while ($Done = cURL_Multi_Info_Read($Master)) {
                $Slave = $Done['handle'];
                $Request = $Requests[(int)$Slave];
                $Name = $Request['Name'];

                $Code = cURL_GetInfo($Slave, CURLINFO_HTTP_CODE);
                $Data = cURL_Multi_GetContent($Slave);

                echo $Name . ' - HTTP ' . $Code . PHP_EOL;

                //cURL_Multi_Remove_Handle( $Master, $Slave );

                if (isset($Done['error'])) {
                    $this->Report[$Name] = Array('status' => self::STATUS_OFFLINE, 'title' => 'cURL Error' // $Done[ 'error' ]
                    );
                } else if ($Code === 0) {
                    $this->Report[$Name] = Array('status' => self::STATUS_OFFLINE, 'title' => 'Timed Out');
                } else if (($Name === 'game') && $Code == 404) {
                    if (strlen($Data) > 10) {
                        $this->Report[$Name] = Array('status' => self::STATUS_ONLINE, 'title' => 'Online');
                    }
                } else if (($Name === 'auth1' || $Name === 'auth2') && $Code == 403) {
                    if (strlen($Data) > 10) {
                        $this->Report[$Name] = Array('status' => self::STATUS_ONLINE, 'title' => 'Online');
                    }
                } else if ($Name === 'con2' && $Code == 404) {
                    if (strlen($Data) > 10) {
                        $this->Report[$Name] = Array('status' => self::STATUS_ONLINE, 'title' => 'Online');
                    }
                } else if ($Name === 'na' && $Code == 404) {
                    if (strlen($Data) > 10) {
                        $this->Report[$Name] = Array('status' => self::STATUS_ONLINE, 'title' => 'Online');
                    }
                } else if ($Code !== ($Name === 'realms' ? 401 : 200)) {
                    $Set = false;

                    if ($Name === 'login' && !Empty($Data)) {
                        $Data = JSON_Decode($Data, true);

                        if (JSON_Last_Error() === JSON_ERROR_NONE && Array_Key_Exists('error', $Data)) {
                            if ($Data['error'] === 'Internal Server Error') {
                                $Set = 'Server Error';
                            } else {
                                $Set = Array_Key_Exists('errorMessage', $Data) ? $Data['errorMessage'] : $Data['error'];

                                if (StrLen($Set) > 23) {
                                    $Set = SubStr($Set, 0, 23) . '...';
                                }
                            }

                            $this->Report[$Name] = Array('status' => self::STATUS_OFFLINE, 'title' => $Set);
                        }
                    }

                    if ($Set === false) {
                        $this->Report[$Name] = Array('status' => self::STATUS_OFFLINE, 'title' => 'HTTP Error ' . $Code);
                    }

                    unset($Set);
                } else if ($this->{$Request['Callback']}($Data) !== true) {
                    $this->Report[$Name] = Array('status' => self::STATUS_OFFLINE, 'title' => 'Unexpected Response');
                } else {
                    if (cURL_GetInfo($Slave, CURLINFO_TOTAL_TIME) > 1.5) {
                        $this->Report[$Name] = Array('status' => self::STATUS_PERF_DEGRADATION, 'title' => 'Quite Slow');
                    } else {
                        $this->Report[$Name] = Array('status' => self::STATUS_ONLINE, 'title' => 'Online');
                    }
                }

                cURL_Multi_Remove_Handle($Master, $Slave);
                cURL_Close($Slave);

                unset($Request, $Slave, $Data, $Code);
            }

            if ($Running) {
                cURL_Multi_Select($Master, 3.0);
            }
        } while ($Running);

        cURL_Multi_Close($Master);
    }

    private static function CreateSlave($URL, $Timeout) {
        $Slave = cURL_Init();

        cURL_SetOpt_Array($Slave, Array(CURLOPT_URL => $URL, CURLOPT_USERAGENT => self::USER_AGENT, CURLOPT_HEADER => 0, CURLOPT_AUTOREFERER => 1, CURLOPT_RETURNTRANSFER => 1, CURLOPT_FOLLOWLOCATION => 0, CURLOPT_TIMEOUT => $Timeout, CURLOPT_SSL_VERIFYPEER => 1, CURLOPT_SSL_VERIFYHOST => 2));

        return $Slave;
    }

    public function GetReport() {
        return $this->Report;
    }

    private function CheckGame($Data) {
        return StrLen($Data) > 10;
    }

    private function CheckAuth0($Data) {
        return StrLen($Data) > 10;
        return true;
    }

    private function CheckAuth1($Data) {
        return StrLen($Data) > 10;
        return true;
    }

    private function CheckAuth2($Data) {
        return StrLen($Data) > 10;
        return true;
    }

    private function CheckCon1($Data) {
        return true;
    }

    private function CheckCon2($Data) {
        return StrLen($Data) > 10;
        return true;
    }

    private function CheckAustralia($Data) {
        return true;
    }

    private function CheckGermany($Data) {
        return true;
    }

    private function CheckItaly($Data) {
        return true;
    }

    private function CheckNetherlands($Data) {
        return true;
    }

    private function CheckNewzealand($Data) {
        return true;
    }

    private function CheckPortugal($Data) {
        return true;
    }

    private function CheckSpain($Data) {
        return true;
    }

    private function CheckUK($Data) {
        return true;
    }

    private function CheckUS($Data) {
        return true;
    }

    private function CheckNA($Data) {
        return StrLen($Data) > 10;
        return true;
    }

    private function CheckOther($Data) {
        return true;
    }


    private function PingServer($ip) {
        return fsockopen($ip, 80, $errno, $errstr, 10);
    }
}
