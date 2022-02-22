# tatum-metamask-nft
Demo for creating a multichain NFT marketplace using MetaMask and the Tatum API. This only works when you mint from the same account as the contract was created.

It's not a finished application but the code should help understand the process of creating an NFT from creating a contract, taking a payment, setting up the NFT data and minting the NFT.

The user can choose their prefered blockchain to mint the NFT to:

https://user-images.githubusercontent.com/60509953/154858691-3d4e2993-72c4-4b39-aa0d-dd3fc1cb14d2.mp4


To keep things simple and avoid storing private keys anywhere it uses a two step payment system where the payment and gas fees are two separate transactions.

### Stages:
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


Full process demonstration video including console log: https://streamable.com/hospo2 
