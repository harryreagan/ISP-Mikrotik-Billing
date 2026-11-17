<!-- Floating Support Hanging Widget for ISP Billing -->
<div id="hsmart-isp-support-widget" class="fixed bottom-6 right-6 z-50 font-sans print:hidden" style="position: fixed; bottom: 24px; right: 24px; z-index: 99999;">
    <!-- Floating Trigger Button (Compact, Elegant, Pulsating) -->
    <button
        id="hsmart-isp-support-trigger"
        type="button"
        onclick="toggleIspSupportDrawer()"
        class="group flex items-center gap-2 bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-500 hover:to-teal-600 text-white px-3.5 py-2.5 rounded-full shadow-xl hover:shadow-emerald-500/30 transition-all duration-300 transform hover:-translate-y-0.5 focus:outline-none ring-2 ring-emerald-500/20 cursor-pointer"
        style="display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #059669, #0f766e); color: #ffffff; padding: 10px 16px; border-radius: 9999px; box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.4); border: 2px solid rgba(16, 185, 129, 0.3); cursor: pointer; transition: all 0.2s ease;"
        title="Open HSmart HQ Support Desk"
    >
        <div style="position: relative; display: flex; width: 10px; height: 10px;">
            <span style="position: absolute; display: inline-flex; width: 100%; height: 100%; border-radius: 9999px; background-color: #6ee7b7; opacity: 0.75; animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;"></span>
            <span style="position: relative; display: inline-flex; border-radius: 9999px; width: 10px; height: 10px; background-color: #34d399;"></span>
        </div>
        <svg style="width: 16px; height: 16px; color: #ffffff;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-4 4z" />
        </svg>
        <span style="font-size: 11px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #ffffff;">Support</span>
    </button>

    <!-- Support Modal / Drawer -->
    <div
        id="hsmart-isp-support-drawer"
        style="display: none; width: min(calc(100vw - 32px), 400px); max-height: 85vh; background: #ffffff; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid #e2e8f0; overflow: hidden; flex-direction: column; position: absolute; bottom: 56px; right: 0;"
    >
        <!-- Header -->
        <div style="background: #0f172a; padding: 14px 18px; color: #ffffff; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #1e293b;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 12px; background: linear-gradient(135deg, #10b981, #14b8a6); display: flex; align-items: center; justify-content: center; font-weight: 900; color: #022c22; font-size: 15px;">
                    ⚡
                </div>
                <div>
                    <h3 style="font-size: 13px; font-weight: 800; margin: 0; color: #ffffff; letter-spacing: -0.01em;">HSmart HQ Support</h3>
                    <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                        <span style="width: 7px; height: 7px; border-radius: 9999px; background-color: #34d399; display: inline-block;"></span>
                        <span style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: #34d399; letter-spacing: 0.05em;">Direct to Super Admin</span>
                    </div>
                </div>
            </div>
            <button
                type="button"
                onclick="toggleIspSupportDrawer()"
                style="background: transparent; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; padding: 4px; line-height: 1; border-radius: 8px;"
                title="Close"
            >&times;</button>
        </div>

        <!-- Quick Contacts Bar -->
        <div style="background: #f8fafc; padding: 8px 16px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; font-size: 11px;">
            <a href="https://wa.me/254790014720?text=Hello%20Harry,%20I%20am%20managing%20ISP%20Billing%20and%20need%20assistance." target="_blank" rel="noopener noreferrer" style="color: #059669; font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 5px;">
                <span>💬 WhatsApp Harry</span>
            </a>
            <a href="tel:+254790014720" style="color: #475569; font-weight: 600; text-decoration: none;">
                📞 +254 790 014 720
            </a>
        </div>

        <!-- Content Area -->
        <div style="padding: 16px; overflow-y: auto; max-height: calc(85vh - 120px);">
            <!-- Success Confirmation Banner -->
            <div id="hsmart-support-success" style="display: none; padding: 14px; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; margin-bottom: 14px; color: #065f46; font-size: 12px;">
                <div style="font-weight: 800; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                    <span>✅ Request Dispatched to Super Admin</span>
                </div>
                <p style="margin: 0; line-height: 1.4; color: #047857;">
                    Your inquiry has been delivered directly to Super Admin Harry. We will respond via phone, WhatsApp, or in the platform shortly.
                </p>
            </div>

            <!-- Error Banner -->
            <div id="hsmart-support-error" style="display: none; padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; margin-bottom: 14px; color: #991b1b; font-size: 12px;">
                <span id="hsmart-support-error-msg">Failed to send request. Please try WhatsApp directly.</span>
            </div>

            <!-- Ticket Form -->
            <form id="hsmart-isp-support-form" onsubmit="submitIspSupportTicket(event)" style="display: flex; flex-direction: column; gap: 12px; font-size: 12px;">
                <div>
                    <label style="display: block; font-weight: 700; color: #334155; margin-bottom: 4px;">Subject / Issue *</label>
                    <input
                        type="text"
                        id="hsmart-ticket-subject"
                        required
                        placeholder="e.g. MikroTik Sync, MPesa STK, PPPoE routing"
                        style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 12px; outline: none; box-sizing: border-box;"
                    />
                </div>

                <!-- Quick Topic Chips -->
                <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                    <button type="button" onclick="setQuickTopic('MikroTik Router Sync Issue')" style="background: #f1f5f9; border: 1px solid #e2e8f0; padding: 3px 8px; border-radius: 8px; font-size: 10.5px; color: #475569; font-weight: 600; cursor: pointer;">
                        ⚙️ MikroTik Sync
                    </button>
                    <button type="button" onclick="setQuickTopic('MPesa / Payment Gateway Help')" style="background: #f1f5f9; border: 1px solid #e2e8f0; padding: 3px 8px; border-radius: 8px; font-size: 10.5px; color: #475569; font-weight: 600; cursor: pointer;">
                        💳 MPesa / Gateway
                    </button>
                    <button type="button" onclick="setQuickTopic('PPPoE / Hotspot Configuration')" style="background: #f1f5f9; border: 1px solid #e2e8f0; padding: 3px 8px; border-radius: 8px; font-size: 10.5px; color: #475569; font-weight: 600; cursor: pointer;">
                        🌐 PPPoE / Hotspot
                    </button>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <label style="display: block; font-weight: 700; color: #334155; margin-bottom: 4px;">Priority</label>
                        <select
                            id="hsmart-ticket-priority"
                            style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 12px; background: #ffffff; box-sizing: border-box;"
                        >
                            <option value="medium">Normal Priority</option>
                            <option value="high">High Priority</option>
                            <option value="urgent">🚨 Urgent (Outage)</option>
                            <option value="low">Low Priority</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 700; color: #334155; margin-bottom: 4px;">Your Contact Phone</label>
                        <input
                            type="text"
                            id="hsmart-ticket-phone"
                            value="{{ auth()->user()?->phone ?? '' }}"
                            placeholder="+2547..."
                            style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 12px; box-sizing: border-box;"
                        />
                    </div>
                </div>

                <div>
                    <label style="display: block; font-weight: 700; color: #334155; margin-bottom: 4px;">Describe Your Request / Issue *</label>
                    <textarea
                        id="hsmart-ticket-message"
                        required
                        rows="3"
                        placeholder="Please describe what you need assistance with..."
                        style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 12px; outline: none; resize: vertical; box-sizing: border-box;"
                    ></textarea>
                </div>

                <button
                    type="submit"
                    id="hsmart-ticket-submit-btn"
                    style="background: linear-gradient(135deg, #059669, #0f766e); color: #ffffff; font-weight: 800; padding: 10px; border-radius: 12px; border: none; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);"
                >
                    <span>Send Support Request to HQ</span>
                    <span>&rarr;</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function toggleIspSupportDrawer() {
    const drawer = document.getElementById('hsmart-isp-support-drawer');
    if (!drawer) return;
    if (drawer.style.display === 'none' || !drawer.style.display) {
        drawer.style.display = 'flex';
    } else {
        drawer.style.display = 'none';
    }
}

function setQuickTopic(topic) {
    const subInput = document.getElementById('hsmart-ticket-subject');
    if (subInput) {
        subInput.value = topic;
    }
}

async function submitIspSupportTicket(event) {
    event.preventDefault();
    const btn = document.getElementById('hsmart-ticket-submit-btn');
    const successBox = document.getElementById('hsmart-support-success');
    const errorBox = document.getElementById('hsmart-support-error');
    const errorMsg = document.getElementById('hsmart-support-error-msg');

    const subject = document.getElementById('hsmart-ticket-subject').value;
    const priority = document.getElementById('hsmart-ticket-priority').value;
    const phone = document.getElementById('hsmart-ticket-phone').value;
    const message = document.getElementById('hsmart-ticket-message').value;

    const fullMessage = (phone ? 'Contact Phone: ' + phone + '\n\n' : '') + message;

    btn.disabled = true;
    btn.innerHTML = 'Sending...';
    successBox.style.display = 'none';
    errorBox.style.display = 'none';

    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('{{ route("hub.support.ticket") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                subject: subject,
                priority: priority,
                message: fullMessage
            })
        });

        const data = await res.json();
        if (res.ok && data.success) {
            successBox.style.display = 'block';
            document.getElementById('hsmart-isp-support-form').reset();
        } else {
            errorMsg.innerText = data.message || 'Could not submit request. Please WhatsApp Harry directly.';
            errorBox.style.display = 'block';
        }
    } catch (e) {
        errorMsg.innerText = 'Network error. Please reach Harry directly via WhatsApp: +254 790 014 720.';
        errorBox.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Send Support Request to HQ &rarr;';
    }
}
</script>

