<?php

class StatusChecker {
    // Useragent
    const USER_AGENT = 'Pokémon GO Status Checker (https://github.com/pavog/pokemonstatus)';

    // Statuses as string
    const STATUS_OFFLINE = 'down';
    const STATUS_ONLINE = 'up';
    const STATUS_PERF_DEGRADATION = 'problem';

    private $Report = Array();
    private $AccessToken = false;
    private $SelectedProfile;

    public function __construct() {
        $Checks = Array(
            Array('Name' => 'game', 'Callback' => 'CheckGame', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            Array('Name' => 'australia', 'Callback' => 'CheckAustralia', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            Array('Name' => 'germany', 'Callback' => 'CheckGermany', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            Array('Name' => 'italy', 'Callback' => 'CheckItaly', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            Array('Name' => 'netherlands', 'Callback' => 'CheckNetherlands', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            Array('Name' => 'newzealand', 'Callback' => 'CheckNewzealand', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            Array('Name' => 'portugal', 'Callback' => 'CheckPortugal', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            Array('Name' => 'spain', 'Callback' => 'CheckSpain', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            Array('Name' => 'uk', 'Callback' => 'CheckUk', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            Array('Name' => 'us', 'Callback' => 'CheckUs', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe'),
            Array('Name' => 'other', 'Callback' => 'CheckOther', 'Timeout' => 4, 'URL' => 'https://pgorelease.nianticlabs.com/plfe')
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

                if ($Name === 'news') {
                    HandleNews($Data, isset($Done['error']) ? 0 : $Code);
                } else if (isset($Done['error'])) {
                    $this->Report[$Name] = Array('status' => self::STATUS_OFFLINE, 'title' => 'cURL Error' // $Done[ 'error' ]
                    );
                } else if ($Code === 0) {
                    $this->Report[$Name] = Array('status' => self::STATUS_OFFLINE, 'title' => 'Timed Out');
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

                /*if( $this->SessionID !== false )
                {
                    echo 'Got it ' . $Name . PHP_EOL;

                    $SlaveNew = $this->CreateSlave( 'https://sessionserver.mojang.com/session/minecraft/join', 3 );

                    cURL_SetOpt_Array( $SlaveNew, Array(
                        CURLOPT_POST       => true,
                        CURLOPT_POSTFIELDS => '{"accessToken":"' . $this->SessionID . '"}',
                        CURLOPT_HTTPHEADER => Array( 'Content-Type: application/json' )
                    ) );

                    $this->SessionID = false;

                    $Requests[ (int)$SlaveNew ] = Array( 'Name' => 'session_auth', 'Callback' => 'CheckSessionReal' );

                    cURL_Multi_Add_Handle( $Master, $SlaveNew );

                    unset( $SlaveNew );
                }*/

                cURL_Multi_Remove_Handle($Master, $Slave);
                cURL_Close($Slave);

                unset($Request, $Slave, $Data, $Code);
            }

            if ($Running) {
                cURL_Multi_Select($Master, 3.0);
            }
        } while ($Running);

        cURL_Multi_Close($Master);

        /*
        if ($this->AccessToken !== false) {
            $Slave = $this->CreateSlave('https://sessionserver.mojang.com/session/minecraft/join', 3);

            cURL_SetOpt_Array($Slave, Array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{"accessToken":"' . $this->AccessToken . '","selectedProfile":"' . $this->SelectedProfile . '","serverId":0}', CURLOPT_HTTPHEADER => Array('Content-Type: application/json')));

            $Data = cURL_Exec($Slave);
            $Code = cURL_GetInfo($Slave, CURLINFO_HTTP_CODE);

            cURL_Close($Slave);

            if ($Code !== 0 && $Code !== 200) {
                $Data = JSON_Decode($Data, true);

                if (JSON_Last_Error() === JSON_ERROR_NONE && Is_Array($Data) && Array_Key_Exists('error', $Data)) {
                    $Set = $Data['error'];

                    if (StrLen($Set) > 23) {
                        $Set = SubStr($Set, 0, 23) . '...';
                    }

                    $this->Report['session'] = Array('status' => self::STATUS_OFFLINE, 'title' => $Set);
                }
            }
        }
        */
    }

    public function GetReport() {
        return $this->Report;
    }

    private static function CreateSlave($URL, $Timeout) {
        $Slave = cURL_Init();

        cURL_SetOpt_Array($Slave, Array(CURLOPT_URL => $URL, CURLOPT_USERAGENT => self::USER_AGENT, CURLOPT_HEADER => 0, CURLOPT_AUTOREFERER => 1, CURLOPT_RETURNTRANSFER => 1, CURLOPT_FOLLOWLOCATION => 0, CURLOPT_TIMEOUT => $Timeout, CURLOPT_SSL_VERIFYPEER => 1, CURLOPT_SSL_VERIFYHOST => 2));

        return $Slave;
    }

    private function CheckLogin($Data) {
        $Data = JSON_Decode($Data, true);

        if (JSON_Last_Error() !== JSON_ERROR_NONE || !Array_Key_Exists('accessToken', $Data)) {
            return false;
        }

        if (!Array_Key_Exists('selectedProfile', $Data)) {
            return false;
        }

        $this->AccessToken = $Data['accessToken'];
        $this->SelectedProfile = $Data['selectedProfile']['id'];

        return true;
    }

    private function CheckRealms($Data) {
        return true;
        //return $Data === 'Invalid session id';
    }

    private function CheckSession($Data) {
        $Data = JSON_Decode($Data, true);

        if (JSON_Last_Error() !== JSON_ERROR_NONE || !Array_Key_Exists('Status', $Data)) {
            return false;
        }

        return $Data['Status'] === 'OK';
    }

    private function CheckWebsite($Data) {
        return StrLen($Data) > 1000 && StrPos($Data, 'is a trademark of') !== false;
    }

    private function CheckSkins($Data) {
        return MD5($Data) === '2041a4dc31f673cf32ca944f6ef460fc';
    }

    private function CheckGame($Data) {
        return StrLen($Data) > 10;
    }
}
