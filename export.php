<?php
/*
Copyright @randomsmncalias (github.com/randomsmncalias)
I created this script to easily export SMNC explorer data so people can use it for taxes or such.

--IMPORTANT--

--LIABILITY--
I can NOT be held liable for anything at all. I created this script in my free time while working an intense fulltime job.
I can not guarantee that it works the way it's intended but I'll try my best to fix issues.
--/LIABILIY--

Feel free to submit issues/bug reports but don't expect anything fast, I have a very limited amount of energy which is assigned accordingly.

Also, if you get any value out of this script, please consider starring it and/or donating some SMNC! See the address in the repo.


I did try to use DRY as much as I could but quite often, I cannot think as clear after an extensive day of working so forgive me for that.
*/

//I picked a random one, please adjust it to your timezone although I haven't tested it with others yet.
date_default_timezone_set("Europe/Berlin");
$args = $argv;

$wallet = get_arg("--wallet");
$format = get_arg("--format");
$ofile = get_arg("--output");

$data = json_decode(file_get_contents("http://explorer.smnccoin.com/ext/getaddress/$wallet"), true);

$ready_tx_data = [];

foreach($data["last_txs"] as $key => $a_data) {
    $tx_data = json_decode(file_get_contents("http://explorer.smnccoin.com/ext/gettx/".$a_data["addresses"]), true);
    $in_out_data = in_out_data($tx_data["tx"]["vin"], $tx_data["tx"]["vout"], $wallet);
    if(arg_exists("--start") || arg_exists("--end")) {
        $start = get_arg("--start");
        $end = get_arg("--end");
        $start_time = (int) human_to_epoch($start);
        $end_time = (int) human_to_epoch($end);
        $tx_time = $tx_data["tx"]["timestamp"];
        if($tx_time >= $start_time && $tx_time <= $end_time) {
            switch(get_arg("--format")) {
                case "csv":
                    if(isset($in_out_data["multiple"])) {
                        foreach($in_out_data["multiple"] as $m) {
                            $ready_tx_data[] = [epoch_to_human($tx_time), get_amount_from_default($m["amount"]), $m["direction"], $a_data["addresses"]];
                        }
                    }
                    $ready_tx_data[] = [epoch_to_human($tx_time), get_amount_from_default($in_out_data["amount"]), $in_out_data["direction"], $a_data["addresses"]];
                break;
                case "json":
                    if(isset($in_out_data["multiple"])) {
                        foreach($in_out_data["multiple"] as $m) {
                            $ready_tx_data[] = ["timestamp" => epoch_to_human($tx_time), "amount" => $m["amount"], "direction" => $m["direction"], "tx" => $a_data["addresses"]];
                        }
                    }
                    $ready_tx_data[] = ["timestamp" => epoch_to_human($tx_time), "amount" => $in_out_data["amount"], "direction" => $in_out_data["direction"], "tx" => $a_data["addresses"]];
            }
        }
    } else {
        switch(get_arg("--format")) {
            case "csv":
                if(isset($in_out_data["multiple"])) {
                    foreach($in_out_data["multiple"] as $m) {
                        $ready_tx_data[] = [epoch_to_human($tx_data["tx"]["timestamp"]), get_amount_from_default($m["amount"]), $m["direction"], $a_data["addresses"]];
                    }
                } else {
                    $ready_tx_data[] = [epoch_to_human($tx_data["tx"]["timestamp"]), get_amount_from_default($in_out_data["amount"]), $in_out_data["direction"], $a_data["addresses"]];
                }
               
            break;
            case "json":
                if(isset($in_out_data["multiple"])) {
                    foreach($in_out_data["multiple"] as $m) {
                        $ready_tx_data[] = ["timestamp" => epoch_to_human($tx_data["tx"]["timestamp"]), "amount" => $m["amount"], "direction" => $m["direction"], "tx" => $a_data["addresses"]];
                    }
                } else {
                    $ready_tx_data[] = ["timestamp" => epoch_to_human($tx_data["tx"]["timestamp"]), "amount" => $in_out_data["amount"], "direction" => $in_out_data["direction"], "tx" => $a_data["addresses"]];
                }
        }
    }
}


    switch(get_arg("--format")) {
        case "csv":
            $fp = fopen($ofile, 'w');
            if(!$fp) {
                die("File is not writeable or the permissions aren't correct.".PHP_EOL);
            }
            foreach ($ready_tx_data as $fields) {
                fputcsv($fp, $fields, ",");
            }
            fclose($fp);
        break;
        case "json":
            if(count($ready_tx_data) > 0) {
                if(file_put_contents($ofile, json_encode($ready_tx_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                    die("Results saved to $ofile, exiting...".PHP_EOL);
                } else {
                    die("File permission issue maybe? Can't write to the given location/file. Exiting...".PHP_EOL);
                }
            }
        break;
        default:
            $fp = fopen($ofile, 'w');
            if(!$fp) {
                die("File is not writeable or the permissions aren't correct.");
            }
            foreach ($ready_tx_data as $fields) {
                fputcsv($fp, $fields, ",");
            }
            fclose($fp);
    }

function epoch_to_human($epoch) {
    $datetime = new DateTime("@".$epoch);
    return $datetime->format('d-m-Y H:i:s');
}

function human_to_epoch($datetime) {
    $datetime = new DateTime($datetime);
    return $datetime->format('U');
}

function get_amount_from_default($amount) {
    $a = strrev(substr(strrev($amount), 8));
    $number = (string) @number_format($a);
    if($number == 0 || $number == "0")
        return "0.".strrev(substr(strrev($amount), 0,8));
    else {
        return $number.".".strrev(substr(strrev($amount), 0,8));
    }
}


//For some fucking reason the outgoing ones are in the 'vin' array and the incoming ones in the 'vout'.....
//Don't ask my why, I find it weird and unnatural.
function in_out_data($vin, $vout, $wallet) {

    $m = in_out_multiple($vin, $vout, $wallet);

    $multiple = [];

    if(is_array($vin)) {
        foreach($vin as $ia) {
            if($wallet == $ia["addresses"]) {
                if($m) {
                    $multiple["multiple"][] = [
                        "direction" => "out",
                        "amount" => get_amount_from_default($ia["amount"]),
                    ];
                } else {
                    return [
                        "direction" => "out",
                        "amount" => get_amount_from_default($ia["amount"]),
                    ];
                }
            }
        }
    }
    if(is_array($vout)) {
        foreach($vout as $io) {
            if($wallet == $io["addresses"]) {
                if($m) {
                    $multiple["multiple"][] = [
                        "direction" => "in",
                        "amount" => get_amount_from_default($io["amount"]),
                    ];
                } else {
                    return [
                        "direction" => "in",
                        "amount" => get_amount_from_default($io["amount"]),
                    ];
                }
            }
        }
    }
    return $multiple;
}

function in_out_multiple($vin, $vout, $wallet) {
    $vi = false;
    $vo = false;
    if(is_array($vin)) {
        foreach($vin as $ia) {
            if($wallet == $ia["addresses"]) {
                $vi = true;
            }
        }
    }
    if(is_array($vout)) {
        foreach($vout as $io) {
            if($wallet == $io["addresses"]) {
                $vo = true;
            }
        }
    }

    if($vi && $vo) {
        return true;
    } else {
        return false;
    }
}

function get_arg($arg) {
    global $argc, $argv;
    $args = $argv;
    foreach($args as $k => $v) {
        if($v == $arg) {
            if($k+1 <= count($args)) {
                return $args[$k+1];
            } else {
               echo "Argument $arg's value does not exist, exiting...".PHP_EOL;
               usage();
            }
        }
    }
    echo "Argument $arg's value does not exist, exiting...".PHP_EOL;
    usage();
    
}

function arg_exists($arg) {
    global $argc, $argv;
    $args = $argv;
    foreach($args as $k => $v) {
        if($v == $arg) {
            if($k+1 <= count($args)-1) {
                return true;
            } else {
               return false;
            }
        }
    }
}

function usage() {
    echo "Usage: php export.php [args]".PHP_EOL;
    echo "\t--wallet [wallet_address] (required)".PHP_EOL;
    echo "\t--format [json/csv] (defaults to csv)".PHP_EOL;
    echo "\t--output [output filename] (relative path (tested on Linux)) (required)".PHP_EOL;
    echo "\t--start [timestamp between double quotes] ("DD-MM-YYYY HH:MM:SS")".PHP_EOL;
    echo "\t--end [timestamp between double quotes] ("DD-MM-YYYY HH:MM:SS")".PHP_EOL;
    echo PHP_EOL;
    die;
}


function get_date_time($epoch) {
    $datetime = new DateTime("@$epoch");
    return $datetime->format('d-m-Y H:i:s');
}
