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
import "@xterm/xterm/css/xterm.css";

const props = defineProps({ server: Object });

const terminalRef = ref(null);
const isConnected = ref(false);
const isClosing = ref(false);
let terminal = null;
let fitAddon = null;
let socket = null;

const initTerminal = () => {
    terminal = new Terminal({
        cursorBlink: true,
        fontSize: 14,
        fontFamily: "JetBrains Mono, Fira Code, monospace",
        theme: {
            background: "#1a1b26",
            foreground: "#a9b1d6",
            cursor: "#c0caf5",
        },
    });
    fitAddon = new FitAddon();
    terminal.loadAddon(fitAddon);
    terminal.open(terminalRef.value);
    fitAddon.fit();

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
            isConnected.value = true;
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
            isConnected.value = false;
            if (event.code !== 1000 && event.code !== 1001) {
                terminal.writeln(`\x1b[31mConnection closed (code: ${event.code})\x1b[0m`);
            }
        };
        socket.onerror = (event) => {
            terminal.writeln(`\x1b[31mWebSocket error.\x1b[0m`);
        };
    } catch (error) {
        terminal.writeln(`\x1b[31mFailed to get session token: ${error.message}\x1b[0m`);
    }
};

onMounted(() => {
    initTerminal();
    connect();
});

onBeforeUnmount(() => {
    socket?.close();
});
</script>

<template>
    <Head :title="`Terminal — ${server.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div
                    class="h-2 w-2 rounded-full shrink-0"
                    :class="isConnected ? 'bg-green-500' : 'bg-red-500'"
                />
                <span class="text-sm font-medium">
                    {{ server.name }} — {{ server.host }}:{{ server.port }}
                </span>
            </div>

            <div class="ml-auto">
                <Button variant="outline" size="sm" class="rounded-sm" :disabled="isClosing" @click="disconnect">
                    <LogOut class="h-4 w-4 mr-1" />
                    Disconnect
                </Button>
            </div>
        </template>

        <div class="p-4" ref="terminalRef" />
    </AuthenticatedLayout>
</template>
