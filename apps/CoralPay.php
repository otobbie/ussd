<?php


class CoralPay
{
    public function list_bank_page($page)
    {

        switch ($page) {
            case 1:
                $ret = "1. ACCESS\n2. ACCESS(DIAMOND)\n3. ACCION MFB\n4.  CITIBANK\n5. ECOBANK\n6. ENTERPRISE\n7. FCMB\n8. FIDELITY\n9.FIRST BANK\n10. MORE";
                break;
            case 2:
                $ret = "1. GTB\n2. HERITAGE\n3. JAIZ\n4. KEYSTONE\n5. KUDA MFB\n6. PAGA\n7. POLARIS\n8. PRESTIGE\n9. PROVIDUS\n10. MORE";
                break;
            case 3:
                $ret = "1. SAFETRUST\n2. STANBIC IBTC\n3. STANDARD CHARTERED\n4. STERLING\n5. SUNTRUST\n6. TAJ BANK\n7. TITAN TRUST\n8. TAGPAY\n9. TEASY MOBILE\n10. MORE";
                break;
            case 4:
                $ret = "1. UNIBEN MFB\n2. UNICAL MFB\n3. UNION\n4. UBA\n5. UNITY\n6. VFD MFB\n7. WEMA\n8. ZENITH";
                break;
            default:
                $ret = "1. ACCESS\n2. ACCESS(DIAMOND)\n3. ACCION MFB\n4.  CITIBANK\n5. ECOBANK\n6. ENTERPRISE\n7. FCMB\n8. FIDELITY\n9.FIRST BANK\n0. MORE";
                break;
        }
        return $ret;
    }

    public function get_bank($page, $option)
    {
        switch ($page) {
            case 1:
                $bank_codes =
                    [
                        1 => "000014,Access Bank",
                        2 => "000005,Access (Diamond)",
                        3 => "090134,Accion MfB",
                        4 => "000009,Citibank",
                        5 => "000010,Ecobank",
                        6 => "000019,Enterprise Bank",
                        7 => "000003,FCMB",
                        8 => "000007,Fidelity Bank",
                        9 => "000016,First Bank"
                    ];
                $code = $bank_codes[$option];
                break;
            case 2:
                $bank_codes =
                    [
                        1 => "000013,GTBank",
                        2 => "000020,Heritage Bank",
                        3 => "000006,Jaiz Bank",
                        4 => "000002,Keystone Bank",
                        5 => "090267,Kuda MfB",
                        5 => "100002,PAGA",
                        7 => "000008,Polaris Bank",
                        8 => "090274,Prestige",
                        9 => "000023,Providus"
                    ];
                $code = $bank_codes[$option];
                break;
            case 3:
                $bank_codes =
                    [
                        1 => "090006,SafeTrust",
                        2 => "000012,Stanbic IBTC",
                        3 => "000021,Standard Chartered",
                        4 => "000001,Sterling Bank",
                        5 => "000022,SunTrust",
                        6 => "000026,TAJ Bank",
                        7 => "000025,Titan Trust",
                        8 => "100023,TAGPAY",
                        9 => "100010,TEASY Mobile"

                    ];
                $code = $bank_codes[$option];
                break;
            case 4:
                $bank_codes =
                    [
                        1 => "090266,UNIBEN MfB",
                        2 => "090193,UNICAL MfB",
                        3 => "000018,Union Bank",
                        4 => "000004,UBA",
                        5 => "000011,Unity Bank",
                        6 => "090110,VFB MfB",
                        7 => "000017,Wema Bank",
                        8 => "000015,Zenith Bank",
                    ];
                $code = $bank_codes[$option];
                break;
            default:
                break;
        }
        # explode to an array
        # $bank_code = $arr[0], $bank_name = $arr[1];
        $list = explode(",", $code);
        return [
            "code" => $list[0],
            "name" => $list[1]
        ];
    }

    public function valid_option($opt)
    {
        # must enter 1-9
        return ($opt >= 1) && ($opt <= 9);
    }
}
