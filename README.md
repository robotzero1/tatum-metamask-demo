# tatum-metamask-nft
Starter code for creating a multichain NFT marketplace using MetaMask and the Tatum API

To keep things simple and avoid storing private keys anywhere it uses a two step payment system where the payment and gas fees are two separate transactions.

The user can choose their prefered blockchain

Stages:
User selects the chain they want to mint to in MetaMask
Button becomes available
User clicks button
Payment request from Tatum/KMS
MetaMask opens for payment confirmation
MetaMask waits for on-chain confirmation
Confirmation checked with Tatum
NFT image sent to the IPFS
Image hash returned
NFT data sent to the IPFS
Data hash returned
Mint request sent to Tatum
Payment request from Tatum/KMS
MetaMask opens for mint fee confirmation
MetaMask waits for on-chain confirmation
Item mint check with Tatum
Process complete - page refreshes
NFT details retrieved from IPFS and shown on page




Video demonstration: https://streamable.com/hospo2 with the console open to see details of the process.
