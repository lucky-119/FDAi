// Function to extract the data
function extractAndSaveAmazon() {
  // Send a message to the background script to navigate to the Amazon order history page
  chrome.runtime.sendMessage({action: "navigate", url: "https://www.amazon.com/gp/css/order-history"});

  // Rest of your function code here...
}
