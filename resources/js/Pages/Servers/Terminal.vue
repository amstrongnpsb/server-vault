<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from "vue";
import { Head, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Terminal } from "@xterm/xterm";
import { FitAddon } from "@xterm/addon-fit";
import { LogOut } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { toast } from "vue-sonner";
import axios from "axios";
import { useTheme } from "@/composables/useTheme";
import "@xterm/xterm/css/xterm.css";

const props = defineProps({ server: Object });

const terminalRef = ref(null);
const connectionState = ref('disconnected'); // 'connecting' | 'connected' | 'disconnected'
const isClosing = ref(false);
let terminal = null;
let fitAddon = null;
let socket = null;

const fitTerminal = () => {
    nextTick(() => fitAddon?.fit());
};

const { isDark } = useTheme();

const initTerminal = () => {
    terminal = new Terminal({
        cursorBlink: true,
        fontSize: 14,
        fontFamily: "JetBrains Mono, Fira Code, monospace",
        theme: isDark.value
            ? { background: "#020a14", foreground: "#abb0af", cursor: "#36d9b8" }
            : { background: "#fefefe", foreground: "#2a2a2a", cursor: "#36d9b8" },
    });
    fitAddon = new FitAddon();
    terminal.loadAddon(fitAddon);
    terminal.open(terminalRef.value);
    fitTerminal();

    terminal.onData((data) => {
        if (socket?.readyState === WebSocket.OPEN) {
            socket.send(data);
        }
    });
};

const disconnect = () => {
    if (isClosing.value) return;
    isClosing.value = true;

    socket?.close();
    router.visit(route("servers.index"));
};

const connect = async () => {
    connectionState.value = 'connecting';
    let sessionId = null;
    try {
        const { data } = await axios.post(
            route("servers.connect", props.server.id),
        );
        sessionId = data.session_id;

        const wsUrl = `${data.bridge_url}?token=${data.token}`;
        terminal.writeln(`\x1b[2mConnecting to ${wsUrl}...\x1b[0m`);

        socket = new WebSocket(wsUrl);
        socket.binaryType = "arraybuffer";
        socket.onopen = () => {
            connectionState.value = 'connected';
            terminal.focus();
        };
        socket.onmessage = (event) => {
            if (event.data instanceof ArrayBuffer) {
                const decoder = new TextDecoder("utf-8");
                terminal.write(decoder.decode(event.data));
            } else {
                terminal.write(event.data);
            }
        };
        socket.onclose = (event) => {
            connectionState.value = 'disconnected';
            if (event.code !== 1000 && event.code !== 1001) {
                terminal.writeln(`\x1b[31mConnection closed (code: ${event.code})\x1b[0m`);
            }
        };
        socket.onerror = () => {
            connectionState.value = 'disconnected';
            terminal.writeln(`\x1b[31mWebSocket error.\x1b[0m`);
        };
    } catch (error) {
        connectionState.value = 'disconnected';
        terminal.writeln(`\x1b[31mFailed to get session token: ${error.message}\x1b[0m`);
    }
};

onMounted(() => {
    initTerminal();
    connect();

    const observer = new ResizeObserver(fitTerminal);
    observer.observe(terminalRef.value);
    terminalRef.value._resizeObserver = observer;
});

onBeforeUnmount(() => {
    socket?.close();
    terminalRef.value?._resizeObserver?.disconnect();
});
</script>

<template>
    <Head :title="`Terminal — ${server.name}`" />

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
                    {{ server.name }} — {{ server.host }}:{{ server.port }}
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

        <div class="w-full h-[calc(100vh-65px)] overflow-hidden rounded-none sm:rounded-lg border-x-0 sm:border-x border-border" ref="terminalRef" />
    </AuthenticatedLayout>
</template>

<style>
.xterm {
    height: 100%;
    padding: 8px;
}
.xterm-viewport {
    scrollbar-width: thin;
}
</style>
