window.addEventListener("DOMContentLoaded", () => {

    
const sendChatBtn = document.querySelector(".chatinput span");
const chatInput = document.querySelector(".chatinput textarea");
const chattoggler = document.querySelector(".chatbot-toggler");
const chattCloseBtn = document.querySelector(".close-btn");
const chatbox = document.querySelector(".chatbox");

var Visibility = false;

let ThreadId
async function createThread() {
    //onAjaxChatbotCreateThread
    if(ThreadId){
        console.log('thread salva: ', ThreadId)
        return ThreadId}
    else{
        return fetch("index.php?option=com_ajax&format=json&plugin=chatbotCreateThread",{
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            ThreadId = data.thread_id
            console.log("threadId:", ThreadId)
            return ThreadId
        })
        .catch(error => console.error('Erro:', error));
    }
}

async function genarateResponse(usermessage, incomingMsg) {
    const messageElemant = incomingMsg.querySelector("p")

    return fetch("index.php?option=com_ajax&format=json&plugin=chatbotCreateRun", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ "message": usermessage })
    })
    .then(response => response.json()) 
    .then(data => {
        console.log('Resposta recebida:', data);
        if(!data){
            throw new Error('Resposta vazia')
        }
        messageElemant.textContent = data.value
    })
    .catch(error => { console.error('Erro na requisição:', error);})
    .finally(() => {
        chatbox.scrollTo(0, chatbox.scrollHeight);
    });
}

const addMessage = () => {
    
    const userMessage = chatInput.value.trim();
    if (!userMessage) return;

    chatInput.value = "";
    chatInput.style.height = `${inputInitHeight}px` ;
    
    const outgoingMsg = document.createElement("li");
    outgoingMsg.classList.add("chat", "outgoing");
    outgoingMsg.innerHTML = `<p>${userMessage}</p>`;
    chatbox.appendChild(outgoingMsg);

    const incomingMsg = document.createElement("li");
    incomingMsg.classList.add("chat", "incoming");
    incomingMsg.innerHTML = `<span class="material-symbols-outlined">smart_toy</span><p>...</p>`;
    chatbox.appendChild(incomingMsg);

    genarateResponse(userMessage, incomingMsg);
}

const togglebotVisibility = () => {
    Visibility = !Visibility
    document.getElementById("bot-show").classList.toggle("show");
    document.getElementById("bot-toggler").classList.toggle("show-t");
}

const inputInitHeight = 40//chatInput.scrollHeight - 10;
chatInput.addEventListener("input", () => {
    chatInput.style.lineHeight = (chatInput.scrollHeight >= 50)? '20px':'30px'
    
    chatInput.style.height = `${inputInitHeight}px` ;
    chatInput.style.height = (chatInput.scrollHeight - 10) < 100 ? `${chatInput.scrollHeight - 20}px`: `90px` ;
})


// Eventos

sendChatBtn.addEventListener("click", addMessage)
chatInput.addEventListener("keydown", (e) => {
    //console.log(window.innerWidth);
    if(e.key === "Enter" && !e.shiftKey && window.innerWidth > 800 ) {
        e.preventDefault();
        addMessage();
    }
})

chattoggler.addEventListener("click", () => {
    togglebotVisibility();
    if (Visibility){createThread()};
})

chattCloseBtn.addEventListener("click", () => togglebotVisibility())

});