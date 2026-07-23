<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from "vue";
import { Head, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { LogOut } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { toast } from "vue-sonner";
import axios from "axios";
import Guacamole from "guacamole-common-js";

const props = defineProps({ server: Object });

const displayContainer = ref(null);
const connectionState = ref("disconnected");
const isClosing = ref(false);
let client = null;
let tunnel = null;
let keyboard = null;

const disconnect = () => {
    if (isClosing.value) return;
    isClosing.value = true;

    document.body.classList.remove('rdp-active');
    if (keyboard) { keyboard.onkeydown = null; keyboard.onkeyup = null; }
    tunnel?.disconnect();
    router.visit(route("servers.index"));
};

const connect = async () => {
    connectionState.value = "connecting";
    try {
        const { data } = await axios.post(
            route("servers.rdp-connect", props.server.id),
        );

        const protocol = window.location.protocol === "https:" ? "wss:" : "ws:";
        const wsUrl = `${protocol}//${window.location.host}/rdp-ws`;

        tunnel = new Guacamole.WebSocketTunnel(wsUrl);

        client = new Guacamole.Client(tunnel);

        const display = client.getDisplay();
        const element = display.getElement();
        displayContainer.value.appendChild(element);

        client.onerror = (error) => {
            toast.error(`RDP error: ${error.message}`);
            connectionState.value = "disconnected";
        };

        const fitToContainer = () => {
            const container = displayContainer.value;
            if (!container || !client) return;

            const cw = container.clientWidth;
            const ch = container.clientHeight;
            const dw = client.getDisplay().getWidth();
            const dh = client.getDisplay().getHeight();

            if (dw === 0 || dh === 0 || cw === 0 || ch === 0) return;

            const scale = Math.min(cw / dw, ch / dh);
            client.getDisplay().scale(scale);
        };

        const resizeObserver = new ResizeObserver(() => {
            const container = displayContainer.value;
            if (!container) return;
            const w = container.clientWidth;
            const h = container.clientHeight;
            if (w > 0 && h > 0) {
                client.sendSize(w, h);
                fitToContainer();
            }
        });

        client.onstatechange = (state) => {
            if (state === 3) {
                connectionState.value = "connected";
                nextTick(() => {
                    fitToContainer();
                    resizeObserver.observe(displayContainer.value);
                });
                displayContainer.value?.querySelector('canvas')?.focus();
            }
        };

        client.onname = (name) => {
            toast.info(`Connected to: ${name}`);
        };

        client.onclipboard = (stream, mimetype) => {
            const reader = new Guacamole.StringReader(stream);
            let text = "";
            reader.ontext = (chunk) => { text += chunk; };
            reader.onend = () => {
                navigator.clipboard?.writeText(text);
            };
        };

        const mouse = new Guacamole.Mouse(element);

        const sendScaledMouseState = (state) => {
            const currentScale = client.getDisplay().getScale() || 1;

            const scaledState = new Guacamole.Mouse.State(
                state.x / currentScale,
                state.y / currentScale,
                state.left,
                state.middle,
                state.right,
                state.up,
                state.down,
            );

            client.sendMouseState(scaledState);
        };

        mouse.onmousedown = mouse.onmouseup = mouse.onmousemove = sendScaledMouseState;

        keyboard = new Guacamole.Keyboard(document);
        keyboard.onkeydown = (keysym) => client.sendKeyEvent(1, keysym);
        keyboard.onkeyup = (keysym) => client.sendKeyEvent(0, keysym);

        client.connect("token=" + encodeURIComponent(data.token));
    } catch (error) {
        connectionState.value = "disconnected";
        toast.error(`Failed to connect: ${error.message}`);
    }
};

onMounted(() => {
    document.body.classList.add('rdp-active');
    connect();
});

onBeforeUnmount(() => {
    document.body.classList.remove('rdp-active');
    if (keyboard) { keyboard.onkeydown = null; keyboard.onkeyup = null; }
    tunnel?.disconnect();
    displayContainer.value?._resizeObserver?.disconnect();
});
</script>

<template>
    <Head :title="`Remote Desktop — ${server.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div
                    class="h-2 w-2 rounded-full shrink-0"
                    :class="{
                        'bg-green-500': connectionState === 'connected',
                        'bg-yellow-500 animate-pulse': connectionState === 'connecting',
                        'bg-red-500': connectionState === 'disconnected',
                    }"
                />
                <span class="text-sm font-medium">
                    {{ server.name }} — {{ server.host }}:3389
                </span>
            </div>

            <div class="ml-auto">
                <Button
                    variant="outline"
                    size="sm"
                    class="rounded-sm"
                    :disabled="connectionState === 'connecting' || isClosing"
                    @click="disconnect"
                >
                    <LogOut class="h-4 w-4 mr-1" />
                    <template v-if="connectionState === 'connecting'">Connecting...</template>
                    <template v-else>Disconnect</template>
                </Button>
            </div>
        </template>

        <div
            ref="displayContainer"
            class="rdp-display flex items-center justify-center w-full h-[calc(100vh-65px)] overflow-hidden rounded-none sm:rounded-lg border-x-0 sm:border-x border-border bg-black relative"
        />
    </AuthenticatedLayout>
</template>

<style>
body.rdp-active {
    overflow: hidden;
}

.rdp-display canvas,
.rdp-display > div {
    cursor: none;
}
</style>
