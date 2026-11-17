<!-- HSmart HQ Live Chat Desk (Identical to Retail Live Chat) -->
<div id="hsmart-live-chat-root" class="print:hidden">
    <!-- Floating Trigger Button (Compact, Elegant, Pulsating) -->
    <button
        id="hsmart-chat-trigger"
        type="button"
        onclick="toggleIspLiveChat()"
        style="display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #059669, #0f766e); color: #ffffff; padding: 10px 18px; border-radius: 9999px; box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.45); border: 2px solid rgba(16, 185, 129, 0.35); cursor: pointer; transition: all 0.25s ease; position: relative;"
        title="Open HSmart HQ Support Desk"
    >
        <!-- Online Pulse Indicator -->
        <span style="position: relative; display: flex; width: 10px; height: 10px;">
            <span id="isp-chat-pulse" style="position: absolute; display: inline-flex; width: 100%; height: 100%; border-radius: 9999px; background-color: #34d399; opacity: 0.75; animation: ispPing 1.6s cubic-bezier(0, 0, 0.2, 1) infinite;"></span>
            <span style="position: relative; display: inline-flex; border-radius: 9999px; width: 10px; height: 10px; background-color: #10b981;"></span>
        </span>
        <svg style="width: 16px; height: 16px; color: #ffffff;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-4 4z" />
        </svg>
        <span style="font-size: 11.5px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #ffffff;">Support</span>
        <!-- Unread Badge Counter -->
        <span id="isp-chat-badge" style="display: none; position: absolute; top: -6px; right: -6px; background-color: #ef4444; color: #ffffff; font-size: 10px; font-weight: 900; height: 18px; min-width: 18px; padding: 0 5px; border-radius: 9999px; align-items: center; justify-content: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2); border: 2px solid #ffffff;">0</span>
    </button>

    <!-- Live Support Drawer Modal -->
    <div
        id="hsmart-chat-drawer"
        style="display: none; width: min(calc(100vw - 32px), 410px); height: min(600px, 82vh); background: #ffffff; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); border: 1px solid #e2e8f0; overflow: hidden; flex-direction: column; position: absolute; bottom: 58px; right: 0;"
    >
        <!-- Header (Dark Slate High Contrast) -->
        <div style="background: #0f172a; padding: 14px 18px; color: #ffffff; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #1e293b; flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 38px; height: 38px; border-radius: 12px; background: linear-gradient(135deg, #10b981, #14b8a6); display: flex; align-items: center; justify-content: center; font-weight: 900; color: #022c22; font-size: 16px; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3);">
                    ⚡
                </div>
                <div>
                    <h3 style="font-size: 14px; font-weight: 800; margin: 0; color: #ffffff; letter-spacing: -0.01em;">HSmart HQ Support</h3>
                    <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                        <span id="isp-admin-status-dot" style="width: 8px; height: 8px; border-radius: 9999px; background-color: #34d399; display: inline-block;"></span>
                        <span id="isp-admin-status-text" style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: #34d399; letter-spacing: 0.05em;">HQ Support Online 🟢</span>
                    </div>
                </div>
            </div>
            <button
                type="button"
                onclick="toggleIspLiveChat()"
                style="background: transparent; border: none; color: #94a3b8; font-size: 22px; cursor: pointer; padding: 4px; line-height: 1; border-radius: 8px;"
                title="Close"
            >&times;</button>
        </div>

        <!-- Tab Switcher (Live Chat vs Tickets) -->
        <div style="display: flex; background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 6px 10px; gap: 8px; flex-shrink: 0;">
            <button
                type="button"
                id="isp-tab-chat-btn"
                onclick="switchIspTab('chat')"
                style="flex: 1; padding: 7px 12px; border-radius: 12px; font-size: 12px; font-weight: 800; border: none; cursor: pointer; background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.08); display: flex; align-items: center; justify-content: center; gap: 6px;"
            >
                <svg style="width: 14px; height: 14px; color: #059669;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <span>Live Chat</span>
            </button>
            <button
                type="button"
                id="isp-tab-tickets-btn"
                onclick="switchIspTab('tickets')"
                style="flex: 1; padding: 7px 12px; border-radius: 12px; font-size: 12px; font-weight: 700; border: none; cursor: pointer; background: transparent; color: #64748b; display: flex; align-items: center; justify-content: center; gap: 6px;"
            >
                <svg style="width: 14px; height: 14px; color: #6366f1;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                </svg>
                <span>Tickets & Contacts</span>
            </button>
        </div>

        <!-- TAB CONTENT: LIVE CHAT -->
        <div id="isp-tab-chat-content" style="flex: 1; display: flex; flex-direction: column; overflow: hidden; background: #f8fafc;">
            <!-- Sub-bar: Direct Channel -->
            <div style="background: #f1f5f9; padding: 6px 16px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; font-size: 10.5px; color: #475569; font-weight: 600;">
                <span style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 6px; height: 6px; border-radius: 9999px; background: #10b981;"></span>
                    <span>Direct Channel to Super Admin Harry</span>
                </span>
                <span style="color: #64748b; font-family: monospace;">Tenant #{{ auth()->user()?->tenant_id ?? '1' }}</span>
            </div>

            <!-- Messages Stream Container -->
            <div id="isp-chat-stream" style="flex: 1; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 10px;">
                <!-- Loading State -->
                <div id="isp-chat-loading" style="display: flex; justify-content: center; padding: 24px;">
                    <div style="width: 24px; height: 24px; border: 3px solid #cbd5e1; border-top-color: #059669; border-radius: 50%; animation: ispSpin 0.8s linear infinite;"></div>
                </div>

                <!-- Empty State (Shown when no messages yet) -->
                <div id="isp-chat-empty" style="display: none; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 20px 10px; margin: auto 0;">
                    <div style="width: 48px; height: 48px; border-radius: 16px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
                        <svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <h4 style="font-size: 13px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">Start a Live Conversation</h4>
                    <p style="font-size: 11.5px; color: #64748b; margin: 0 0 14px 0; max-width: 260px; line-height: 1.4;">
                        Have questions or need assistance? Send a message directly to HSmart HQ Support below.
                    </p>

                    <!-- Quick Suggestion Chips -->
                    <div style="display: flex; flex-direction: column; gap: 6px; width: 100%; max-width: 320px;">
                        <button type="button" onclick="sendQuickMessage('⚙️ How do I sync my MikroTik router with billing?')" style="background: #ffffff; border: 1px solid #e2e8f0; padding: 7px 12px; border-radius: 10px; font-size: 11.5px; text-align: left; color: #334155; font-weight: 600; cursor: pointer; transition: all 0.2s ease;">
                            ⚙️ How do I sync MikroTik router?
                        </button>
                        <button type="button" onclick="sendQuickMessage('💳 Need help setting up M-Pesa STK push & Paybill')" style="background: #ffffff; border: 1px solid #e2e8f0; padding: 7px 12px; border-radius: 10px; font-size: 11.5px; text-align: left; color: #334155; font-weight: 600; cursor: pointer; transition: all 0.2s ease;">
                            💳 Need help with M-Pesa STK push
                        </button>
                        <button type="button" onclick="sendQuickMessage('🌐 Assistance with PPPoE & Hotspot speed plans')" style="background: #ffffff; border: 1px solid #e2e8f0; padding: 7px 12px; border-radius: 10px; font-size: 11.5px; text-align: left; color: #334155; font-weight: 600; cursor: pointer; transition: all 0.2s ease;">
                            🌐 PPPoE & Hotspot speed plans
                        </button>
                    </div>
                </div>

                <!-- Messages Output List -->
                <div id="isp-messages-list" style="display: flex; flex-direction: column; gap: 10px;"></div>
            </div>

            <!-- Bottom Chat Input Bar -->
            <form id="isp-chat-input-form" onsubmit="submitIspLiveChatMessage(event)" style="background: #ffffff; padding: 10px 14px; border-top: 1px solid #e2e8f0; display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                <input
                    type="text"
                    id="isp-chat-input"
                    autocomplete="off"
                    required
                    placeholder="Type your message to HQ..."
                    style="flex: 1; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 14px; font-size: 12.5px; outline: none; background: #f8fafc; color: #0f172a; box-sizing: border-box;"
                />
                <button
                    type="submit"
                    id="isp-chat-send-btn"
                    style="background: linear-gradient(135deg, #059669, #0f766e); color: #ffffff; border: none; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.3); flex-shrink: 0;"
                    title="Send Message"
                >
                    <svg style="width: 17px; height: 17px; transform: rotate(45deg); margin-right: 2px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </button>
            </form>
        </div>

        <!-- TAB CONTENT: TICKETS & CONTACTS -->
        <div id="isp-tab-tickets-content" style="display: none; flex: 1; flex-direction: column; padding: 16px; overflow-y: auto; background: #ffffff;">
            <!-- Direct WhatsApp Banner -->
            <a href="https://wa.me/254790014720?text=Hello%20Harry,%20I%20am%20managing%20ISP%20Billing%20and%20need%20assistance." target="_blank" rel="noopener noreferrer" style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 14px; padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; text-decoration: none; margin-bottom: 14px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; border-radius: 10px; background: #059669; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                        💬
                    </div>
                    <div>
                        <div style="font-size: 12.5px; font-weight: 800; color: #065f46;">Chat with Harry on WhatsApp</div>
                        <div style="font-size: 11px; color: #047857;">Instant emergency support desk</div>
                    </div>
                </div>
                <span style="font-size: 12px; font-weight: 800; color: #059669;">Open &rarr;</span>
            </a>

            <!-- Direct Call Banner -->
            <a href="tel:+254790014720" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; text-decoration: none; margin-bottom: 14px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; border-radius: 10px; background: #0f172a; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 15px;">
                        📞
                    </div>
                    <div>
                        <div style="font-size: 12.5px; font-weight: 800; color: #0f172a;">Direct Phone Call</div>
                        <div style="font-size: 11px; color: #64748b;">+254 790 014 720</div>
                    </div>
                </div>
                <span style="font-size: 12px; font-weight: 800; color: #475569;">Call &rarr;</span>
            </a>

            <!-- Support Tickets Overview -->
            <div style="margin-top: 8px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                    <h4 style="font-size: 12.5px; font-weight: 800; color: #0f172a; margin: 0;">Support Tickets</h4>
                    <a href="{{ route('admin-tickets') }}" style="font-size: 11.5px; font-weight: 700; color: #059669; text-decoration: none;">View All &rarr;</a>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; font-size: 11.5px; color: #64748b; line-height: 1.5;">
                    💡 Need formal ticket tracking? You can manage tenant and customer support tickets in your dedicated tickets manager, or chat with Super Admin Harry in real-time on the <strong>Live Chat</strong> tab.
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#hsmart-live-chat-root {
    position: fixed !important;
    right: 24px !important;
    bottom: 24px !important;
    z-index: 99999 !important;
    font-family: 'Jost', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
}

@media (max-width: 767.98px) {
    #hsmart-live-chat-root {
        right: 14px !important;
        bottom: 74px !important;
    }
}

@keyframes ispPing {
    0% { transform: scale(1); opacity: 0.8; }
    75%, 100% { transform: scale(2.4); opacity: 0; }
}
@keyframes ispSpin {
    to { transform: rotate(360deg); }
}
</style>

<script>
let ispChatOpen = false;
let ispChatPollTimer = null;
let ispUnreadPollTimer = null;
let ispChatActiveTab = 'chat';
let ispKnownMessagesCount = 0;

function toggleIspLiveChat() {
    const drawer = document.getElementById('hsmart-chat-drawer');
    if (!drawer) return;

    ispChatOpen = !ispChatOpen;
    if (ispChatOpen) {
        drawer.style.display = 'flex';
        fetchIspMessages(true);
        startIspChatPolling();
        // Hide badge when opened
        const badge = document.getElementById('isp-chat-badge');
        if (badge) badge.style.display = 'none';
    } else {
        drawer.style.display = 'none';
        stopIspChatPolling();
        fetchIspUnreadCount();
    }
}

function switchIspTab(tab) {
    ispChatActiveTab = tab;
    const chatBtn = document.getElementById('isp-tab-chat-btn');
    const ticketsBtn = document.getElementById('isp-tab-tickets-btn');
    const chatContent = document.getElementById('isp-tab-chat-content');
    const ticketsContent = document.getElementById('isp-tab-tickets-content');

    if (tab === 'chat') {
        chatBtn.style.background = '#ffffff';
        chatBtn.style.color = '#0f172a';
        chatBtn.style.boxShadow = '0 1px 3px rgba(0,0,0,0.08)';
        ticketsBtn.style.background = 'transparent';
        ticketsBtn.style.color = '#64748b';
        ticketsBtn.style.boxShadow = 'none';
        chatContent.style.display = 'flex';
        ticketsContent.style.display = 'none';
        scrollIspChatBottom();
    } else {
        ticketsBtn.style.background = '#ffffff';
        ticketsBtn.style.color = '#0f172a';
        ticketsBtn.style.boxShadow = '0 1px 3px rgba(0,0,0,0.08)';
        chatBtn.style.background = 'transparent';
        chatBtn.style.color = '#64748b';
        chatBtn.style.boxShadow = 'none';
        ticketsContent.style.display = 'flex';
        chatContent.style.display = 'none';
    }
}

function scrollIspChatBottom() {
    setTimeout(() => {
        const stream = document.getElementById('isp-chat-stream');
        if (stream) {
            stream.scrollTop = stream.scrollHeight;
        }
    }, 50);
}

function playIspChime() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();
        const now = ctx.currentTime;
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, now); // D5
        gain1.gain.setValueAtTime(0.18, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.22);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.22);

        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(880, now + 0.12); // A5
        gain2.gain.setValueAtTime(0.2, now + 0.12);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.12);
        osc2.stop(now + 0.45);
    } catch (e) {}
}

async function fetchIspMessages(initial = false) {
    const loading = document.getElementById('isp-chat-loading');
    const emptyState = document.getElementById('isp-chat-empty');
    const list = document.getElementById('isp-messages-list');

    try {
        const res = await fetch('/api/live-chat/messages', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (loading) loading.style.display = 'none';

        if (data.success && Array.isArray(data.messages)) {
            const msgs = data.messages;

            // Check if new admin message arrived
            if (!initial && msgs.length > ispKnownMessagesCount) {
                const last = msgs[msgs.length - 1];
                if (last && last.sender_role === 'super_admin') {
                    playIspChime();
                }
            }
            ispKnownMessagesCount = msgs.length;

            if (msgs.length === 0) {
                if (emptyState) emptyState.style.display = 'flex';
                if (list) list.innerHTML = '';
            } else {
                if (emptyState) emptyState.style.display = 'none';
                renderIspMessages(msgs);
                scrollIspChatBottom();
            }

            // Update admin status indicator
            const statusDot = document.getElementById('isp-admin-status-dot');
            const statusText = document.getElementById('isp-admin-status-text');
            if (data.is_admin_online) {
                if (statusDot) statusDot.style.backgroundColor = '#34d399';
                if (statusText) statusText.innerText = 'HQ Support Online 🟢';
            } else {
                if (statusDot) statusDot.style.backgroundColor = '#fbbf24';
                if (statusText) statusText.innerText = 'HQ Support Away 🟡';
            }
        }
    } catch (e) {
        if (loading) loading.style.display = 'none';
    }
}

function renderIspMessages(messages) {
    const list = document.getElementById('isp-messages-list');
    if (!list) return;

    list.innerHTML = messages.map(msg => {
        const isAdmin = (msg.sender_role === 'super_admin');
        const time = msg.formatted_time || (msg.created_at ? new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '');

        if (isAdmin) {
            return `
                <div style="display: flex; flex-direction: column; align-items: flex-start; max-width: 85%;">
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; border-top-left-radius: 4px; padding: 10px 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-size: 12px; color: #0f172a; line-height: 1.45;">
                        <div style="font-size: 9.5px; font-weight: 900; text-transform: uppercase; color: #4338ca; margin-bottom: 4px; display: flex; align-items: center; gap: 4px; letter-spacing: 0.05em;">
                            <span style="width: 14px; height: 14px; border-radius: 50%; background: #e0e7ff; display: inline-flex; align-items: center; justify-content: center; font-size: 9px;">⚡</span>
                            <span>HSmart HQ Support</span>
                        </div>
                        <div style="white-space: pre-wrap; word-break: break-word;">${escapeHtml(msg.message)}</div>
                    </div>
                    <span style="font-size: 9.5px; color: #94a3b8; margin-top: 3px; margin-left: 6px;">${time}</span>
                </div>
            `;
        } else {
            return `
                <div style="display: flex; flex-direction: column; align-items: flex-end; margin-left: auto; max-width: 85%;">
                    <div style="background: linear-gradient(135deg, #059669, #0f766e); color: #ffffff; border-radius: 18px; border-top-right-radius: 4px; padding: 10px 14px; box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.25); font-size: 12px; line-height: 1.45;">
                        <div style="white-space: pre-wrap; word-break: break-word;">${escapeHtml(msg.message)}</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 4px; margin-top: 3px; margin-right: 6px;">
                        <span style="font-size: 9.5px; color: #94a3b8;">${time}</span>
                        <span style="font-size: 9.5px; color: #10b981;">✓✓</span>
                    </div>
                </div>
            `;
        }
    }).join('');
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function sendQuickMessage(text) {
    const input = document.getElementById('isp-chat-input');
    if (input) {
        input.value = text;
        submitIspLiveChatMessage(new Event('submit'));
    }
}

async function submitIspLiveChatMessage(event) {
    if (event) event.preventDefault();
    const input = document.getElementById('isp-chat-input');
    const sendBtn = document.getElementById('isp-chat-send-btn');
    if (!input || !input.value.trim()) return;

    const messageText = input.value.trim();
    input.value = '';

    // Optimistically render message immediately
    const list = document.getElementById('isp-messages-list');
    const emptyState = document.getElementById('isp-chat-empty');
    if (emptyState) emptyState.style.display = 'none';

    const tempDiv = document.createElement('div');
    tempDiv.style.cssText = "display: flex; flex-direction: column; align-items: flex-end; margin-left: auto; max-width: 85%;";
    tempDiv.innerHTML = `
        <div style="background: linear-gradient(135deg, #059669, #0f766e); color: #ffffff; border-radius: 18px; border-top-right-radius: 4px; padding: 10px 14px; box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.25); font-size: 12px; line-height: 1.45;">
            <div style="white-space: pre-wrap; word-break: break-word;">${escapeHtml(messageText)}</div>
        </div>
        <div style="display: flex; align-items: center; gap: 4px; margin-top: 3px; margin-right: 6px;">
            <span style="font-size: 9.5px; color: #94a3b8;">Sending...</span>
        </div>
    `;
    list.appendChild(tempDiv);
    scrollIspChatBottom();

    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('/api/live-chat/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ message: messageText })
        });
        const data = await res.json();
        if (data.success) {
            fetchIspMessages();
        }
    } catch (e) {}
}

async function fetchIspUnreadCount() {
    if (ispChatOpen) return;
    try {
        const res = await fetch('/api/live-chat/unread-count', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        const badge = document.getElementById('isp-chat-badge');
        if (badge) {
            const count = parseInt(data.unread_count || 0);
            if (count > 0) {
                badge.innerText = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    } catch (e) {}
}

function startIspChatPolling() {
    stopIspChatPolling();
    ispChatPollTimer = setInterval(() => {
        if (ispChatOpen && ispChatActiveTab === 'chat') {
            fetchIspMessages();
        }
    }, 3500);
}

function stopIspChatPolling() {
    if (ispChatPollTimer) {
        clearInterval(ispChatPollTimer);
        ispChatPollTimer = null;
    }
}

// Background unread badge poll every 35 seconds
setInterval(fetchIspUnreadCount, 35000);
// Initial unread fetch after 3 seconds
setTimeout(fetchIspUnreadCount, 3000);
</script>
