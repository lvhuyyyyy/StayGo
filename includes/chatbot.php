<!-- Nút mở -->
<div class="chat-button" onclick="toggleChat()">
    <img src="https://img.freepik.com/premium-vector/woman-with-headphones-microphone-with-laptop_1023984-24634.jpg" alt="Trợ lý StayGo">
</div>

<!-- Hộp chat -->
<div class="chat-box" id="chatBox">
    <div class="chat-header">
        <div class="chat-header-left">
            <img src="https://img.freepik.com/premium-vector/woman-with-headphones-microphone-with-laptop_1023984-24634.jpg" alt="Trợ lý StayGo">
            <div>
                <strong>Nhân viên trợ giúp StayGo</strong><br>
                <small>Online</small>
            </div>
        </div>
        <span style="cursor:pointer" onclick="toggleChat()">×</span>
    </div>

    <div class="chat-content" id="chatContent"></div>

    <div class="chat-input">
        <input
            type="text"
            id="message"
            placeholder="Nhập câu hỏi..."
            aria-label="Nhập câu hỏi cho trợ lý"
            autocomplete="off"
            onkeypress="if(event.key==='Enter') sendMessage();"
        >
        <button onclick="sendMessage()">Gửi</button>
    </div>
</div>
