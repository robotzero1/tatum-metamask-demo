<?php

include('../database-connector/db.php');


$headerAPI = "your x-api-key";

if (isset($_GET['item'])){
    
    $itemIDchainID = explode("|", $_GET['item']);
    $itemID = intval($itemIDchainID[0]);
    $chainID = intval($itemIDchainID[1]);
    
    
    // ITEM
    $sql = "SELECT * FROM item WHERE id_item = ?";
    $stmtItem = $pdo->prepare($sql);
    $stmtItem->execute([$itemID]);

    // CHAIN
    $sql = "SELECT * FROM chain WHERE id_chain = ?";
    $stmtChain = $pdo->prepare($sql);
    $stmtChain->execute([$chainID]);
    
    $item = $stmtItem->fetch(PDO::FETCH_ASSOC);
    $chain = $stmtChain->fetch(PDO::FETCH_ASSOC);
    
    $tokenID = $item['item_issue_number'];
    
    // CONTRACT
    $sql = "SELECT * FROM contract WHERE contract_cat_id = ? AND contract_chain_id = ?";
    $stmtContract = $pdo->prepare($sql);
    $stmtContract->execute([ $item['category_id'], $chainID ]);     
    
    $contract = $stmtContract->fetch(PDO::FETCH_ASSOC);  
    
    $feeCurrency = 'CELO'; // CELO minting

}

if (isset($_GET['address'])){
    $customerAddress = filter_var($_GET['address'], FILTER_SANITIZE_URL);
}
if (isset($_GET['transactionTX'])){
    $transactionTX = filter_var($_GET['transactionTX'], FILTER_SANITIZE_URL);
}
if (isset($_GET['ipfs'])){
    $ipfsHash = filter_var($_GET['ipfs'], FILTER_SANITIZE_URL);
}
if (isset($_GET['name'])){
    $ipfsName = filter_var($_GET['name'], FILTER_SANITIZE_STRING);
}
if (isset($_GET['description'])){
    $ipfsDescription = filter_var($_GET['description'], FILTER_SANITIZE_STRING);
}

//echo "<pre>";
// print_r($item);
// print_r($chain);
//print_r($status);





function oneStopShop(){
    
    global $headerAPI, $pdo, $tokenID, $contractTX, $ipfsName, $ipfsDescription, $ipfsHash, $transactionTX, $customerAddress, $chain, $item, $status, $contract, $feeCurrency;
    

    $action=$_GET['action'];
    
    switch ($action) {
        case 'reserve':
            $itemReserved = reserveItem($pdo, $customerAddress, $chain, $item);
            echo $itemReserved;
            exit;
        case 'checkpayment';
            $transactionData = lookupTX($pdo, $headerAPI, $_GET['chainname'], $transactionTX);
            $transactionMatched = doubleCheck($pdo, $transactionData, $_GET['chainid'], $customerAddress);
            echo $transactionMatched;
            exit;
        case 'image':
            $uploadedFile = uploadtoIPFS($headerAPI,"/home/sequence/public_html/uploads/item-".$item['id_item'].".jpg");
            echo $uploadedFile['ipfsHash'];
            exit;
        case 'meta':
            $meta = addMetaToIPFS($headerAPI, $ipfsName, $ipfsDescription, $ipfsHash);
            echo $meta['ipfsHash'];
            exit;
        case 'mint':
            $sigID = mint($headerAPI, $tokenID, $customerAddress, $chain['chain_ticker'], $contract['contract_address'], $ipfsHash, $contract['contract_uuid'], $feeCurrency);
            $pendingNFT = transactionKMS($headerAPI, $sigID['signatureId']);
            echo $pendingNFT['serializedTransaction'];
            exit;
        case 'checkminted':
            $transactionData = lookupTX($pdo, $headerAPI, $_GET['chainname'], $transactionTX);
            $nftMatched = checkNFTExists($pdo, $transactionData, $_GET['chainid'], $customerAddress);
            $nftMetadata = multifunction($headerAPI, "https://api-eu1.tatum.io/v3/nft/metadata/".$chain['chain_ticker']."/".$contract['contract_address']."/".$tokenID);
            $nftMetaText = multifunction($headerAPI, "https://api-eu1.tatum.io/v3/ipfs/".$nftMetadata['data']);
            echo json_encode($nftMetaText);
            exit;
        case 'transfer':
            $sigID = transfer($headerAPI, $chain['chain_ticker'], $customerAddress, $tokenID, $contract['contract_address'], $contract['contract_uuid']);
            $transferredNFT = transactionKMS($headerAPI, $sigID['signatureId']);
            echo $transferredNFT['serializedTransaction'];
            exit;
            
        
    }
    
    exit;
}

oneStopShop();
exit;

function insertTransactionData($pdo, $itemID,	$ipAddress=NULL, $customerAddress=NULL, $payToAddress=NULL, $chainID=NULL, $amount=NULL, $transactionTX=NULL, $type=NULL)
{
    $sql = "INSERT INTO transaction (transaction_item_id, transaction_ip_address, transaction_from_address, transaction_to_address, transaction_chain_id, transaction_amount, transaction_tx, transaction_type) VALUES (?,?,?,?,?,?,?,?)";
    $stmt= $pdo->prepare($sql);
    $stmt->execute([$itemID, $ipAddress, $customerAddress, $payToAddress, $chainID, $amount, $transactionTX, $type]);
}

function updateItemStatus($pdo, $itemID, $itemStatus, $customerAddress=NULL, $chainID=NULL, $tokenPrice=NULL)
{
    $sql = "REPLACE INTO item_status (item_status_item_id, item_status_status, item_status_address, item_status_chain_id, item_status_token_price) VALUES (?,?,?,?,?)";
    $stmt= $pdo->prepare($sql);
    $stmt->execute([$itemID, $itemStatus, $customerAddress, $chainID, $tokenPrice]);
}

function guidv4($data)
{
    assert(strlen($data) == 16);

    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function reserveItem($pdo, $customerAddress, $chain, $item){
    
    $payToAddress = $chain['chain_payment_to_address'];
    $dollarPrice = $item['item_price'];
    $exchangeRate = $chain['chain_exchange_rate'];
    $gasFee = $chain['chain_fee'];
    $tokenPrice = round($dollarPrice/$exchangeRate, 12);

    try {
        insertTransactionData($pdo, $item['id_item'], $_SERVER['REMOTE_ADDR'], $customerAddress, $payToAddress, $chain['id_chain'], $tokenPrice, NULL, 'reserve');
        updateItemStatus($pdo, $item['id_item'], 'Reserved', $customerAddress, $chain['id_chain'], $tokenPrice);
    }
    catch (exception $e) {
        //code to handle the exception
        error_log($e);
    }
    finally {
        //optional code that always runs
        $fields = array("itemid" => $item['id_item'], "chainid" => $chain['id_chain'], "metamaskid" => $chain['chain_metamask_id'], "tokenprice" => $tokenPrice, "paytoaddress" => $payToAddress);
        return json_encode($fields);
    }

}

function lookupTX($pdo, $headerAPI, $chain, $transactionTX)
{
    
    try {
        insertTransactionData($pdo, NULL, $_SERVER['REMOTE_ADDR'], NULL, NULL, $chain, NULL, $transactionTX, 'checkpayment');
    }
    catch (exception $e) {
        error_log($e);
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api-eu1.tatum.io/v3/".strtolower($chain)."/transaction/".$transactionTX,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
           "x-api-key: $headerAPI"
        ],
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
        return "cURL Error #:" . $err; 
    } else {
        $transactionData = json_decode($response, true);
        return $transactionData;
    }
}

function doubleCheck($pdo, $transactionData, $chainID, $customerAddress){
    
    
    $sql = "SELECT * FROM item_status WHERE item_status_status = ? AND item_status_chain_id = ? AND item_status_address = ?";
    $stmtStatus = $pdo->prepare($sql);
    $stmtStatus->execute(['Reserved', $chainID, $customerAddress]);
    $statusRow = $stmtStatus->fetch(PDO::FETCH_ASSOC); 
    // check row > 0
    
    $sql = "SELECT * FROM chain WHERE id_chain = ?";
    $stmtChain = $pdo->prepare($sql);
    $stmtChain->execute([$chainID]);  
    $chain = $stmtChain->fetch(PDO::FETCH_ASSOC);
    
    $priceTatumStyle = str_replace('.', '', $statusRow['item_status_token_price']);
    $priceTatumStyle = ltrim($priceTatumStyle, '0');
    
    // Check chain and database prices match
    if ( substr($priceTatumStyle, 0, 8) != substr($transactionData['value'], 0, 8) ){// workaround chain price rounding
        // update log, reset status
        $errorText = "Payment doesn't match chain record. Database record: ".substr($priceTatumStyle, 0, 10).". Chain record: ".substr($transactionData['value'], 0, 10);
        return $errorText;
    }
    
    // Check customer and chain addresses match for sending address. Case insensitive.
    if (strcasecmp($customerAddress, $transactionData['from']) != 0){
        // update log, reset status
        $errorText = "From address doesn't match chain record. Address from: ".$customerAddress.". Address on chain: ".$transactionData['from'];
        return $errorText;
    } 
    
    // Check chain and database addresses match for receiving address. Case insensitive.
    if (strcasecmp($chain['chain_payment_to_address'], $transactionData['to']) != 0){
        // update log, reset status   
        $errorText = "To address doesn't match chain record. Address To: ".$chain['chain_payment_to_address'].". Address on chain: ".$transactionData['to'];
        return $errorText;        
    }
    
    $sql = "UPDATE item_status SET item_status_status = 'Paid' WHERE id_status = ?";
    $stmtStatus = $pdo->prepare($sql);
    $stmtStatus->execute([ $statusRow['id_status'] ]);

    return "All checks passed";
}


function multifunction($headerAPI, $curlUrl)
{
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $curlUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
           "x-api-key: $headerAPI"
        ],
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
        return "cURL Error #:" . $err; 
    } else {
        $json = json_decode($response, true);
        return $json;
    }
}



function transactionKMS($headerAPI, $signatureID)
{
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api-eu1.tatum.io/v3/kms/".$signatureID,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
           "x-api-key: $headerAPI",
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $json = json_decode($response, true);
        return $json;
    }
}


function uploadtoIPFS($headerAPI, $imgUrl)
{

    $curl = curl_init();
    $cFile = curl_file_create($imgUrl);
    $post = array('file'=> $cFile);
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api-eu1.tatum.io/v3/ipfs",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_HTTPHEADER => [
            "content-type: multipart/form-data",    
            "x-api-key: $headerAPI",
        ],        
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $json = json_decode($response, true);
        return $json;
    }
}

function addMetaToIPFS($headerAPI, $name, $description, $imageHash)
{
    $metadata = array("name" => $name, "description" => $description, "image" => "ipfs://".$imageHash );
    $metadata = json_encode($metadata);
    $tmpfile = tmpfile();
    fwrite($tmpfile, $metadata);
    $metadataFile = stream_get_meta_data($tmpfile)['uri'];
    $curl = curl_init();
    $cFile = curl_file_create($metadataFile,'image/png','kaiten.json');
    $post = array('file'=> $cFile);
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api-eu1.tatum.io/v3/ipfs",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_HTTPHEADER => [
            "content-type: multipart/form-data",    
            "x-api-key: $headerAPI",
        ],        
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $json = json_decode($response, true);
        return $json;
    }
}


function mint($headerAPI, $tokenID, $depositAddress, $chain, $deployedTo, $ipfsHash, $signatureID, $feeCurrency)
{
    // some extra checks before minting needed
    
    $fields = array("tokenId" => strval($tokenID), "to" => $depositAddress,  "chain" => $chain,  "contractAddress" => $deployedTo, "url" => "ipfs://".$ipfsHash, "signatureId" => $signatureID, "feeCurrency" => $feeCurrency);
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api-eu1.tatum.io/v3/nft/mint",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => json_encode($fields),
        CURLOPT_HTTPHEADER => [
            "x-api-key: $headerAPI",
            "Content-Type: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
      echo "cURL Error #:" . $err;
    } else {
        $json = json_decode($response, true);
        return $json;
    }
}


function checkNFTExists($pdo, $transactionData, $chainID, $customerAddress){
    
    // Find the a row that is 'paid', matches the chain and users address
    $sql = "SELECT * FROM item_status WHERE item_status_status = ? AND item_status_chain_id = ? AND item_status_address = ?";
    $stmtStatus = $pdo->prepare($sql);
    $stmtStatus->execute(['Paid', $chainID, $customerAddress]);
    $statusRow = $stmtStatus->fetch(PDO::FETCH_ASSOC); 
    // check row > 0
    
    // Check Metamask and database addresses match for sending address. Case insensitive.
    if (strcasecmp($customerAddress, $transactionData['from']) != 0){
        // TODO update log, reset status
        $errorText = "From address doesn't match chain record. Address from: ".$customerAddress.". Address on chain: ".$transactionData['from'];
        return $errorText;
    }
    
    // Everything is OK
    $sql = "UPDATE item_status SET item_status_status = 'Minted' WHERE id_status = ?";
    $stmtStatus = $pdo->prepare($sql);
    $stmtStatus->execute([ $statusRow['id_status'] ]);
    
    try {
        insertTransactionData($pdo, $statusRow['item_status_item_id'], $_SERVER['REMOTE_ADDR'], $transactionData['from'], NULL, $chainID, NULL, $transactionData['transactionHash'], 'mint');
    }
    catch (exception $e) {
        error_log($e);
    }    

    return "NFT minted and on-chain";
}


function transfer($headerAPI, $chain, $depositAddress, $tokenID, $deployedTo, $signatureID)
{
    $fields = array("chain" => $chain, "to" => $depositAddress, "tokenId" => strval($tokenID), "contractAddress" => $deployedTo, "signatureId" => $signatureID);
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api-eu1.tatum.io/v3/nft/transaction",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => json_encode($fields),
        CURLOPT_HTTPHEADER => [
            "x-api-key: $headerAPI",
            "Content-Type: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
      echo "cURL Error #:" . $err;
    } else {
        $json = json_decode($response, true);
        return $json;
    }
}

