<?php
include('database-connector/db.php');

$headerAPI = "your x-api-key";
$netType = "testnet";

// ITEM
$sql = "SELECT * FROM item
        LEFT OUTER JOIN category ON item.category_id = category.id_category
        LEFT OUTER JOIN item_status ON item.id_item = item_status.item_status_item_id
        WHERE item.id_item = ?";
$stmtItem = $pdo->prepare($sql);        
$stmtItem->execute([$_GET['id']]);
$item = $stmtItem->fetch(PDO::FETCH_ASSOC);


// One call to the chains table
$sql = "SELECT * FROM chain WHERE chain_type = ?";
$orderBySQL = " ORDER BY chain_name ASC";
$stmtChains = $pdo->prepare($sql.$orderBySQL);
$stmtChains->execute([$netType]);

// Now everything in a array
$chains = $stmtChains->fetchAll(PDO::FETCH_ASSOC);

//echo "<pre>";
//print_r($chains);

// Last time rate and fee was updated
$lastUpdated = new \DateTime($chains[0]['chain_prices_updated']);


// More than 1 hour?
$timeNow = new \DateTime();
$diff = $lastUpdated->diff($timeNow);
$diffHours = $diff->h;
$diffInHours = $diffHours + ($diff->days * 24);
if ($diffInHours > 0){ 
    // Then update array...
    foreach ($chains as $key => $chain) {
        $chains[$key]['chain_exchange_rate'] = getExchangeRate($chain['chain_ticker']);
        $chains[$key]['chain_fee'] = estimateTransactionFee($chain['chain_ticker']);
    }
    //... and update database
    $sql = "UPDATE chain SET chain_exchange_rate=?, chain_fee=? WHERE id_chain=?";
    $stmt= $pdo->prepare($sql);
    $pdo->beginTransaction();
    foreach ($chains as $chain) {
        $stmt->execute([$chain['chain_exchange_rate'], $chain['chain_fee'], $chain['id_chain']]);
    }
    $pdo->commit();
}

function estimateTransactionFee($chain){
    $curl = curl_init();

    curl_setopt_array($curl, [
      CURLOPT_URL => "https://api-eu1.tatum.io/v3/blockchain/estimate",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => "{\"chain\":\"$chain\",\"type\":\"MINT_NFT\",\"enableFungibleTokens\":true,\"enableNonFungibleTokens\":false,\"enableSemiFungibleTokens\":false,\"enableBatchTransactions\":false}",
      CURLOPT_HTTPHEADER => [
        "content-type: application/json",
        "x-api-key: ce7dc8f9-2870-404a-b88d-50bef19aa44e"
      ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
      echo "cURL Error #:" . $err;
    } else {
        $json = json_decode($response, true);
        return $json['gasLimit'] * $json['gasPrice'] * 0.000000001;
    }
}

function getExchangeRate($currency)
{

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api-eu1.tatum.io/v3/tatum/rate/$currency?basePair=USD",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "x-api-key: ce7dc8f9-2870-404a-b88d-50bef19aa44e"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        return "cURL Error #:" . $err; 
    } else {
        $json = json_decode($response, true);
        return $json['value'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <title>Document</title>
</head>
<body>
    <header>
        <img src="assets/logo.png">
        <svg class="menu-icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="bars" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" class="svg-inline--fa fa-bars fa-w-14 fa-3x"><path fill="black" d="M16 132h416c8.837 0 16-7.163 16-16V76c0-8.837-7.163-16-16-16H16C7.163 60 0 67.163 0 76v40c0 8.837 7.163 16 16 16zm0 160h416c8.837 0 16-7.163 16-16v-40c0-8.837-7.163-16-16-16H16c-8.837 0-16 7.163-16 16v40c0 8.837 7.163 16 16 16zm0 160h416c8.837 0 16-7.163 16-16v-40c0-8.837-7.163-16-16-16H16c-8.837 0-16 7.163-16 16v40c0 8.837 7.163 16 16 16z" class=""></path></svg>
    </header>
    <main class="view">
        <a href="nft_list.php?category-id=<?php echo $item['id_category']; ?>" class="detail-back-to-category">< BACK TO COLLECTION</a>
  <div class="card-grid">
    <div class="card">
      <div class="card-header card-image">
        <img src="uploads/item-<?php echo $item['id_item']; ?>.jpg" />
      </div>
      <div class="card-body">
        <div class="detail-item-price">$<?php echo $item['item_price']; ?></div>
        <h1 class="detail-item-title"><?php echo $item['item_name']; ?></h1>
        <div class="detail-item-description"><?php echo $item['item_description']; ?></div>
        <div class="detail-item-info"><span>Collection:</span> <?php echo $item['category_name']; ?></div>
        <div class="detail-item-info"><span>Number/Edition:</span> <?php echo $item['item_issue_number']; ?>/<?php echo $item['category_number_in_issue']; ?></div>
        <div class="detail-item-info"><span>Artist:</span> <?php echo $item['category_artist']; ?></div>
        <div class="detail-item-info"><span>Status:</span> <?php echo isset($item['item_status_status']) ?  $item['item_status_status'] : "Available" ?></div>
        <!--<div style="display: grid;  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));">-->
        <!--    <div>-->
        <!--        <div class="detail-item-category">Collection: <?php echo $item['category_name']; ?></div>-->
        <!--        <div class="detail-item-edition">Number/Edition: <?php echo $item['item_issue_number']; ?>/<?php echo $item['category_number_in_issue']; ?></div>-->
        <!--    </div>-->
        <!--    <div>-->
        <!--        <div class="detail-item-artist">Artist: <?php echo $item['category_artist']; ?></div>-->
        <!--        <div class="detail-item-status">Status: <?php echo isset($item['item_status_status']) ?  $item['item_status_status'] : "Available" ?></div>-->
        <!--    </div>-->
        <!--</div>        -->
      </div>
    </div>
    <div class="card">
        <div class="card-body">
            <?php if ($item['item_status_status'] == 'Minted'){
                // just a quick way to show the minted NFT
                $sql = "SELECT * FROM transaction WHERE transaction_item_id = ? AND transaction_type='mint'";
                $stmtMintedItem = $pdo->prepare($sql);
                $stmtMintedItem->execute([$_GET['id']]);
                $mintedItem = $stmtMintedItem->fetch(PDO::FETCH_ASSOC);
                
                $sql = "SELECT * FROM chain WHERE id_chain = ?";
                $stmtMintedChain = $pdo->prepare($sql);
                $stmtMintedChain->execute([$mintedItem['transaction_chain_id']]);
                $mintedChain = $stmtMintedChain->fetch(PDO::FETCH_ASSOC);                
            ?>
            <div>
                <p>Minted on: <?php echo $mintedChain['chain_name']; ?> <?php echo $mintedChain['chain_type']; ?>
                <a href="<?php echo $mintedChain['chain_explorer']; ?><?php echo $mintedItem['transaction_tx']; ?>">View Transaction</a></p>
                Found on the IPFS:
                <div id="ipfs-name">
                    Name: <span></span>
                </div>
                <div id="ipfs-description">
                    Description: <span></span>
                </div>Image: 
                <div id="ipfs-image">
                </div>
                
                
            </div>
                <script>
                    // just a quick way to show the minted NFT
                    const mintedChain = {};
                    <?php
                    foreach ($chains as $key => $chain) {
                        echo "mintedChain[".$chain['id_chain']."] = {chainname:'".$chain['chain_name']."', chainticker:'".$chain['chain_ticker']."', chaintype:'".$chain['chain_type']."', chainid: ".$chain['id_chain']."};".PHP_EOL;
                        
                    } 
                    ?>
                    <?php echo 'const tx = "'.$mintedItem['transaction_tx'].'"'.PHP_EOL; ?>
                    <?php echo 'const chainID = '.$mintedItem['transaction_chain_id'].PHP_EOL; ?>
                    <?php echo 'const itemAndChain = "'.$_GET['id'].'|'.$mintedItem['transaction_chain_id'].'"'.PHP_EOL; ?>
                    <?php echo 'const currentAccount = "'.$mintedItem['transaction_from_address'].'"'.PHP_EOL; ?>
                    const nftDetails = async (tx) => {
                        var requestURI = "remote.php?";
                        var response = await fetch(requestURI + new URLSearchParams({action: 'checkminted', item: itemAndChain, address: currentAccount, transactionTX: tx, chainname: mintedChain[parseInt(chainID)].chainname, chainid: mintedChain[parseInt(chainID)].chainid, chainticker: mintedChain[parseInt(chainID)].chainticker}))
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        result = await response.json();
                        document.querySelector('#ipfs-name > span').innerText = result.name;
                        document.querySelector('#ipfs-description > span').innerText = result.description;
                        const imageURL = 'https://cloudflare-ipfs.com/ipfs/'+result.image;         
                        const ipfsImage = document.createElement('img');
                        ipfsImage.src = imageURL.replace('ipfs://', '');
                        ipfsImage.style="max-width:100px";
                        document.getElementById('ipfs-image').appendChild(ipfsImage)

                        
                    }
                    nftDetails(tx);


                </script>

            <?php } else { ?>
                <div class="detail-metamask-box">
                    <span><img src="assets/metamask.png" class="detail-metamask-logo"></span><span class="detail-metamask-head">METAMASK<span>            
                    <div class="detail-metamask-select-account">Select an account in MetaMask</div>
                </div>
                <?php if (!$itemRow['item_status_status']){ ?>
                    <?php foreach ($chains as $key => $chain) {
                    $chainPrice = round($item['item_price'] / $chain['chain_exchange_rate'], 8);
                    $chainFee = round($chain['chain_fee'], 8);
                    $chainTotal = $chainPrice + $chainFee;
                    $dollarPriceWithFee = round($chainTotal * $chain['chain_exchange_rate'],2);
                    ?>
                    <div id="<?php echo $item['id_item']."|".$chain['id_chain'];?>" class="detail-chain-block buybutton token-<?php echo $chain['chain_name']; ?>">
                        <div class="detail-chain-icon"><img src="assets/<?php echo $chain['chain_ticker']; ?>.png"></div> 
                        <div class="detail-chain-price"><?php echo $chain['chain_ticker']; ?>: <?php echo $chainPrice; ?> ($<?php echo $dollarPriceWithFee; ?>)</div>
                        <div class="detail-chain-price-info">NFT: <?php echo $chainPrice; ?> FEES: <?php echo $chainFee; ?></div>
                    </div> 
                    <?php } ?>                
                <?php } ?>
            <?php } ?>
        </div>
      </div> 
    </div>        
    </main>
    
    <footer class="list"><hr>
    <img src="assets/luma-logo.png" style="margin-right:22px">
    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" id="body_1" width="82" height="47">
    <g transform="matrix(0.07997893 0 0 0.07997893 -0 22.085417)">
        <path transform="matrix(1 0 0 1 -18.95 -15.26)"  d="M204.47 104.63L139.09 104.63L139.09 224.77L204.47 224.77L204.47 104.63z" stroke="none" fill="#4F37FD" fill-rule="nonzero" />
        <path transform="matrix(1 0 0 1 -18.95 -15.26)"  d="M288.54 15.26L168.39 15.26L168.39 75.33L288.54 75.33L288.54 15.26z" stroke="none" fill="#2CCD9A" fill-rule="nonzero" />
        <path transform="matrix(1 0 0 1 -18.95 -15.26)"  d="M139.09 44.55L19 44.55L19 104.630005L139.09 104.630005L139.09 44.55z" stroke="none" fill="#4F37FD" fill-rule="nonzero" />
        <path transform="matrix(1 0 0 1 -18.95 -15.26)"  d="M921.22 83.63L895.78 83.63L895.78 225.63L920.12006 225.63L920.12006 132.12L921.22003 132.12L969.49005 225.63L970.72003 225.63L1019.21 132.12L1020.21 132.12L1020.21 225.63L1044.21 225.63L1044.21 83.630005L1020.92993 83.630005L971.43 180L970.64 180L921.22 83.63zM370 225.63L394.34 225.63L394.34 104.8L457.91 104.8L457.91 83.7L394.36 83.7L394.36 104.799995L319.31 104.799995L319.31 125.899994L370 125.899994zM582.13 83.630005L582.13 102.68001L627.9 102.68001L627.9 225.68001L652.24005 225.68001L652.24005 102.68001L698 102.68001L698 83.63zM831.83 192.3C 832.02435 195.58342 830.8047 198.79312 828.4789 201.11891C 826.1531 203.4447 822.9434 204.66435 819.66003 204.47L819.66003 204.47L766.92 204.47C 763.6364 204.66496 760.4263 203.4457 758.1 201.12C 755.7743 198.79376 754.555 195.58362 754.75 192.29999L754.75 192.29999L754.75 83.63L730.41 83.63L730.41 191.15C 730.41 211.62999 744.41 225.68 764.93 225.68L764.93 225.68L821.73 225.68C 842.23 225.68 856.25 211.68 856.25 191.15L856.25 191.15L856.25 83.63L831.83 83.63zM532.67 168.56L485.35 168.56L508.68 111.36L509.5 111.36zM522.38 83.56L497.24 83.56L438.24 225.56L461.97998 225.56L476.58997 189.65L541.31995 189.65L555.88995 225.56L581.2 225.56z" stroke="none" fill="#1C1E4F" fill-rule="nonzero" />
    </g>
    </svg>
    </footer>
    
<div id="progress-modal" class="modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <div class="progress-list" id="reserved">Item reserved <div></div></div>
      <div class="progress-list" id="pay-price">Please confirm payment <div></div></div>
      <div class="progress-list" id="pay-wait">Waiting for payment confirmation <div></div></div>
      <div class="progress-list" id="store">Setting up permanent storage <div></div></div>
      <div class="progress-list" id="mint">Minting <div></div></div>
      <div class="progress-list" id="pay-mint">Please confirm minting fee <div></div></div>
      <div class="progress-list" id="mint-wait">Waiting for network confirmation <div></div></div>
      <div class="progress-list" id="complete">Process complete <div></div></div>    
    </div>
  </div> 
<script>
// Get the modal
var modal = document.getElementById("progress-modal");

// Get the button that opens the modal
var btn = document.getElementById("myBtn");

// Get the <span> element that closes the modal
var span = document.getElementsByClassName("close")[0];


// When the user clicks on <span> (x), close the modal
span.onclick = function() {
  modal.style.display = "none";
}
</script>
  

<script src="https://unpkg.com/@metamask/detect-provider/dist/detect-provider.min.js"></script>
<script>
const requestURI = "remote.php?";
const updateModalProgress = (stage) => {
    
    // coded this way to allow restarting the process from any point.
  
    console.log('Modal: ' + stage);
    const progressList = document.getElementsByClassName("progress-list");
    const progressItem = document.querySelector(`#${CSS.escape(stage)}`);

    let siblings = Array.from(progressList);
    let index = siblings.indexOf(progressItem);

    for (let i = 0; i <= index; i++) {
        progressList[i].style.color = 'black';
        progressList[i].style.fontWeight = 'bold';
        progressList[i].style.setProperty("--color", "green");
        progressList[i].style.setProperty("--content", "' ✓'");
    }
    const loader = document.getElementsByClassName("loader");
    while (loader.length){
        loader[0].classList.remove("loader");
    }

    const nextProgressItem = document.querySelector(`#${CSS.escape(stage)}`).nextElementSibling;
    if (typeof(nextProgressItem) != 'undefined' && nextProgressItem != null){
        nextProgressItem.querySelector('div').classList.add("loader");
    }
}


// Use the Metamask chain ID as the key for chain properties
const chain = {};
<?php
foreach ($chains as $key => $chain) {
    echo "chain[".$chain['chain_metamask_id']."] = {chainname:'".$chain['chain_name']."', chainticker:'".$chain['chain_ticker']."', chaintype:'".$chain['chain_type']."', chainid: ".$chain['id_chain']."};".PHP_EOL;
} 
?>

// this returns the provider, or null if it wasn't detected
const provider = async () => {
    let response = await detectEthereumProvider();
    if (response) {
        return response;
    } else {
        console.log('Please install MetaMask!');
    }
}


const handleChainChange = async () => {ethereum.on('chainChanged', (chainId) => {
    console.log('New chain: ' + chainId)
    chainname = chain[parseInt(chainId)].chainname;
    chaintype = chain[parseInt(chainId)].chaintype;   
    console.log('chain changed');
    console.log('Name: '+chainname);
    console.log('Type: '+chaintype); 
    activateButtonsForChains(chainname);
    });
}

const activateButtonsForChains = (chainname) => {
    document.querySelectorAll('.buybutton').forEach(elem => {
        elem.removeEventListener('click', processNFT);
        elem.style.opacity = 0.5;
        elem.style.cursor = 'unset';
    });
    document.querySelectorAll('.token-'+chainname).forEach(elem => { // just one button should be without ALL
        elem.addEventListener('click', processNFT);
        elem.style.opacity = 1;
        elem.style.cursor = 'pointer'; 
    });    
}
    
    

    
const initMetamask = async (chain) => {
    provider()
    .catch(e => {console.log('Provider error: ' + e.message)})
    .then(provider => {
        if (provider !== undefined){
            return startApp(provider);
        }
    })
    .then(async startApp => {
        if (startApp){
            //ethereum.on('accountsChanged', handleAccountsChanged);
            handleChainChange();
            let chainID = await ethereum.request({ method: 'eth_chainId' });
            activateButtonsForChains(chain[parseInt(chainID)].chainname)
        }

    })

}

const startApp = async (provider) => {
    // If the provider returned by detectEthereumProvider is not the same as window.ethereum, something is overwriting it, perhaps another wallet.
    if (provider !== window.ethereum) {
        console.error('Do you have multiple wallets installed?');
        return false;
    } else {
        return true;        
    }
}

initMetamask(chain); 



const processNFT = (event) => {
    const itemAndChain = event.currentTarget.id;
    console.log('Item ID | chain ID: ' + itemAndChain);    
    let currentAccount = null;    
    const requestURI = "remote.php?";
    
    // First check the user has MetaMask ready
    ethereum
        .request({ method: 'eth_requestAccounts' })
        .then(handleAccountsChanged)
        .catch((err) => {
        console.error(err);
    });

    ethereum.on('accountsChanged', handleAccountsChanged);
    
    // For now, 'eth_accounts' will continue to always return an array
    function handleAccountsChanged(accounts) {
        console.log(accounts);
        if (accounts.length === 0) {
        // MetaMask is locked or the user has not connected any accounts
            console.log('Please log into MetaMask.');
            alert("Please log into MetaMask");
        } else if (accounts[0] !== currentAccount) {
            currentAccount = accounts[0];
            doItAll('reserve');
        }
    }
    
    function processError(stage, error){
        console.log('Stage: ' + stage); 
        console.log('Error: ' + JSON.stringify(error));

        // Update modal display
        document.querySelector(`#${CSS.escape(stage)} > div`).classList.remove('loader');
        document.querySelector(`#${CSS.escape(stage)}`).style.setProperty("--color", "red");
        document.querySelector(`#${CSS.escape(stage)}`).style.setProperty("--content", "' ×'");
        let nextSiblings = document.querySelectorAll(`#${CSS.escape(stage)} ~ div`); // https://stackoverflow.com/a/47818979/2030399
        let siblingArray = Array.from(nextSiblings);
        siblingArray.forEach(div => div.classList.add('progress-list-incomplete'));
                        
        
        switch(stage){
            case 'pay-price':
                document.querySelector('#pay-price').insertAdjacentHTML('afterend', '<p class="modal-error">Payment cancelled or failed. Please refresh the page and try again<p>');
                document.querySelector('.modal-error').insertAdjacentText('afterend', "(Reported error: " + error.message + ")");
                break;
            case 'pay-mint':
                document.querySelector('#pay-mint').insertAdjacentHTML('afterend', '<p class="modal-error">Payment cancelled or failed. Please refresh the page and try again<p>');
                document.querySelector('.modal-error').insertAdjacentText('afterend', "(Reported error: " + error.message + ")");
                break;                  
                
        }

        initMetamask(chain);
        throw new Error("System restarted");
    }
    
    
    const doItAll = async (nft_stage, data) => {

        modal.style.display = "block";
        
        console.log('doItAll: ' + nft_stage);  

        switch(nft_stage){

            case 'reserve':
                // Reserve this item in the local database
                updateModalProgress('reserved');
                console.log('Action: Reserving NFT');  
                reservedNFT = await reserve();
                console.log('(Result) Reserved data: ' + reservedNFT); // Users account and NFT chosen
                doItAll('payment', reservedNFT);
                break;

            case 'payment':
                // Request payment via MetaMask
                const reservation = JSON.parse(reservedNFT);
                const item  = reservation.itemid;
                const chain = reservation.metamaskid;
                const price = reservation.tokenprice;
                const payToAdddress = reservation.paytoaddress;
                
                console.log('Action: Waiting for payment via Metamask');           
                payment_returned_tx = await payment(item, chain, price, payToAdddress);
                console.log('Result: Returned payment TX: ' + payment_returned_tx); // Payment            
                doItAll('waitForTransaction');
                break;

            case 'waitForTransaction':
                // Use MetaMask to get transaction ID
                updateModalProgress('pay-price');
                console.log('Action: Waiting for confirmation via MetaMask');
                metamask_returned_found = await waitForTransaction(payment_returned_tx);
                console.log('Result: Metamask returned TX: ' + metamask_returned_found['hash']);             
                doItAll('confirmPayment');
                break;

            case 'confirmPayment':
                // Use Tatum to check transaction ID on chain
                console.log('Action: Waiting for payment confirmation via Tatum');
                updateModalProgress('pay-wait');
                confirm_returned_completed = await confirmPayment(metamask_returned_found['hash']);
                console.log('Result: Tatum confirmed text: ' + JSON.stringify(confirm_returned_completed));
                doItAll('IPFSimage');
                break;

            case 'IPFSimage':
                // Add NFT image to IPFS
                console.log('Action: uploading image for IPFS');            
                ipfs_image_hash = await IPFSimage();
                console.log('Result: Image hash from IPFS: ' + ipfs_image_hash)
                doItAll('IPFSMeta');
                break;

            case 'IPFSMeta':
                // Add metadata to IPFS
                updateModalProgress('store');
                console.log('Action: sending data to IPFS');
                ipfs_meta_hash = await IPFSmeta('<?php echo $item['item_name']; ?>', '<?php echo $item['item_description']; ?>', ipfs_image_hash);
                console.log('Result: Meta hash from IPFS: ' + ipfs_meta_hash)            
                doItAll('mintNFT');
                break;

            case 'mintNFT':
                // Mint via Tatum + KMS
                console.log('Action: Request mint');            
                updateModalProgress('mint');
                minting_returned_tx = await mintNFT(ipfs_meta_hash);
                console.log('Result: Mint TX: ' + minting_returned_tx)
                doItAll('waitForNFTTransaction');
                break;
                
            case 'waitForNFTTransaction':
                // Wait for MetaMask
                console.log('Action: Wait for gas fee payment')
                updateModalProgress('pay-mint');
                metamask_found_nft = await waitForTransaction(minting_returned_tx);
                console.log('Result: MetaMask returned TX: ' + metamask_found_nft['hash'])            
                doItAll('confirmNFTTransaction');
                break;

            case 'confirmNFTTransaction':
                // Confirm NFT minted and get metadata via Tatum
                console.log('Action: Waiting for minted confirmation via Tatum');                
                confirm_mint_returned_completed = await confirmNFTMinted(metamask_found_nft['hash']);
                console.log('Result: Mint confirm returned: ' + confirm_mint_returned_completed)             
                doItAll('confirmMinted');
                break;
                
            case 'confirmMinted':
                updateModalProgress('mint-wait');            
                console.log('Process complete');
                doItAll('showNFT');
                break;
                
            case 'showNFT':
                // Just a page refresh for now
                updateModalProgress('complete');            
                // console.log('confirm_mint_returned_completed: '+confirm_mint_returned_completed);
                // const ipfsData = JSON.parse(confirm_mint_returned_completed);
                // document.querySelector('.card-grid').style.display = "none";            
                // document.querySelector('#ipfs-name > span').innerText = ipfsData.name;
                // document.querySelector('#ipfs-description > span').innerText = ipfsData.description;
                // const imageURL = 'https://cloudflare-ipfs.com/ipfs/'+ipfsData.image;         
                // const ipfsImage = document.createElement('img');
                // ipfsImage.src = imageURL.replace('ipfs://', '');
                // document.getElementById('ipfs-image').appendChild(ipfsImage);
                // console.log('Displaying NFT Details');
                window.location.reload();
                break;            
        }
    }

    

    const reserve = async () => {
        let response = await fetch(requestURI + new URLSearchParams({action: 'reserve', address: currentAccount, item: itemAndChain}))
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.text();
    }    

    const payment = async (item, chain, price, payToAdddress) => {
        //console.log('price: '+price);
        //console.log('price: '+ '0x'+parseInt(price * 1000000000000000000).toString(16) );

        const transactionParameters = {
            nonce: '0x00', // ignored by MetaMask
            to: payToAdddress,
            from: ethereum.selectedAddress,
            value: '0x'+parseInt(price * 1000000000000000000).toString(16),
            chainId: chain, // Used to prevent transaction reuse across blockchains. Auto-filled by MetaMask.
            };

        return await ethereum
            .request({
                method: 'eth_sendTransaction',
                params: [transactionParameters]
            })
            .then((result) => {
                return result;
            })
            .catch((error) => {
                processError('pay-price', error);
            });
    }

    const waitForTransaction = async (txid) => {
        const interval = 2000;
        const maxAttempts = 30;
        let attempts = 0;
        const executePoll = async (resolve, reject) => {
            console.log('Waiting for transaction');
            const result = await ethereum.request({"jsonrpc": "2.0",  method: 'eth_getTransactionByHash', params: [txid], "id": 0});
            attempts++;

            if (result.blockHash != null) {
                return resolve(result);
            } else if (maxAttempts && attempts === maxAttempts) {
                return reject(new Error('Exceeded max attempts'));
            } else {
              setTimeout(executePoll, interval, resolve, reject);
            }
        };
        return new Promise(executePoll);
    }
    
    const confirmPayment = async (txid) => {
        let chainID = await ethereum.request({ method: 'eth_chainId' });
        let response = await fetch(requestURI + new URLSearchParams({action: 'checkpayment', address: currentAccount, transactionTX: txid, chainname: chain[parseInt(chainID)].chainname, chainid: chain[parseInt(chainID)].chainid})); 
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.text();
    }     

    const IPFSimage = async () => {
        let response = await fetch(requestURI + new URLSearchParams({action: 'image', item: itemAndChain}))
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.text();
    }
    
    const IPFSmeta = async (IPFSname, IPFSdescription, IPFSimage) => {
        let response = await fetch(requestURI + new URLSearchParams({action: 'meta', name: IPFSname, description: IPFSdescription, ipfs: IPFSimage}))
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.text();
    }    

    const mintNFT = async (IPFSmeta) => {
        let response = await fetch(requestURI + new URLSearchParams({action: 'mint', address: currentAccount, ipfs: IPFSmeta,  item: itemAndChain}))
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        let result = await response.text();
        //console.log('mint text: ' + result);
        const txConfig = JSON.parse(result)
        const transactionParameters = {
            nonce: '0x00', // ignored by MetaMask
            to: txConfig.to, // Required except during contract publications.
            from: ethereum.selectedAddress, // must match user's active address.
            data: txConfig.data,
            chainId: '0xaef3', // Used to prevent transaction reuse across blockchains. Auto-filled by MetaMask.
            };
            
            
        return await ethereum
            .request({
                method: 'eth_sendTransaction',
                params: [transactionParameters]
            })
            .then((result) => {
                return result;
            })
            .catch((error) => {
                processError('pay-mint', error);
            });        
    }
    
    const confirmNFTMinted = async (txid) => {
        let chainID = await ethereum.request({ method: 'eth_chainId' });
        let response = await fetch(requestURI + new URLSearchParams({action: 'checkminted', item: itemAndChain, address: currentAccount, transactionTX: txid, chainname: chain[parseInt(chainID)].chainname, chainid: chain[parseInt(chainID)].chainid, chainticker: chain[parseInt(chainID)].chainticker}))
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        let result = response.text();
        return result;
  
    }
};


</script>
</body>
</html>
