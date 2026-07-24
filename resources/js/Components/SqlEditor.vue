<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from "vue";
import { EditorView, basicSetup } from "codemirror";
import { EditorState, Compartment } from "@codemirror/state";
import { sql, MySQL, PostgreSQL } from "@codemirror/lang-sql";
import { keymap } from "@codemirror/view";
import { acceptCompletion } from "@codemirror/autocomplete";
import { HighlightStyle, syntaxHighlighting } from "@codemirror/language";
import { tags as t } from "@lezer/highlight";
import { Play, RotateCcw } from "lucide-vue-next";

const props = defineProps({
    dialect: {
        type: String,
        default: "MySQL",
    },
    isDark: {
        type: Boolean,
        default: false,
    },
    tables: {
        type: Array,
        default: () => [],
    },
    currentSchema: {
        type: String,
        default: "",
    },
});

const emit = defineEmits(["execute"]);

const containerRef = ref(null);
let view = null;

// Compartments let us reconfigure parts of the editor (theme, highlight)
// without destroying and recreating the whole EditorView/state.
const highlightCompartment = new Compartment();
const themeCompartment = new Compartment();
const sqlCompartment = new Compartment();

const getSqlDialect = () => {
    return props.dialect === "PostgreSQL" ? PostgreSQL : MySQL;
};

// ---- Syntax highlight colors ----

const lightHighlight = HighlightStyle.define([
    { tag: t.keyword, color: "#c084fc", fontWeight: "bold" }, // lighter purple, readable on black
    { tag: t.string, color: "#4ade80" }, // brighter green
    { tag: t.number, color: "#60a5fa" },
    { tag: t.operator, color: "#cbd5e1" },
    { tag: t.comment, color: "#64748b", fontStyle: "italic" },
    { tag: t.variableName, color: "#22d3ee" },
    { tag: t.punctuation, color: "#cbd5e1" },
    { tag: t.paren, color: "#cbd5e1" },
]);

const darkHighlight = HighlightStyle.define([
    {
        tag: t.keyword,
        color: "oklch(89.532% 0.16358 178.781)",
        fontWeight: "bold",
    },
    { tag: t.string, color: "#11ff00" }, // 'string values'
    { tag: t.number, color: "#2563eb" },
    { tag: t.operator, color: "#fcfcfa" },
    { tag: t.comment, color: "#94a3b8", fontStyle: "italic" },
    { tag: t.variableName, color: "#0891b2" }, // table/column names
    { tag: t.punctuation, color: "#475569" },
    { tag: t.paren, color: "#475569" },
]);

const getHighlight = () =>
    syntaxHighlighting(props.isDark ? darkHighlight : lightHighlight);

const getTheme = () => {
    return EditorView.theme({
        "&": {
            backgroundColor: "transparent",
            height: "100%",
            color: "var(--foreground)",
        },
        ".cm-scroller": {
            fontFamily: "'JetBrains Mono', 'Fira Code', monospace",
            fontSize: "13px",
        },
        ".cm-content": { padding: "8px 12px", caretColor: "var(--active)" },
        ".cm-line": { lineHeight: "1.5" },
        ".cm-cursor": {
            borderLeftColor: "var(--active)",
            borderLeftWidth: "2px",
        },
        ".cm-gutters": { display: "none" },
        "&.cm-focused": { outline: "none" },
        ".cm-selectionBackground": { backgroundColor: "var(--accent)" },
        ".cm-tooltip": {
            backgroundColor: "var(--popover)",
            border: "1px solid var(--border)",
            borderRadius: "6px",
            boxShadow: "0 4px 12px rgba(0,0,0,0.15)",
        },
        ".cm-tooltip-autocomplete ul li": {
            padding: "4px 8px",
            fontFamily: "monospace",
            fontSize: "12px",
        },
        ".cm-tooltip-autocomplete ul li[aria-selected]": {
            backgroundColor: "var(--accent)",
            color: "var(--accent-foreground)",
        },
        ".cm-matchingBracket": {
            backgroundColor: "rgba(255,255,255,0.1)",
            outline: "1px solid var(--active)",
        },
    });
};

// ---- Create editor ----

const createEditor = () => {
    if (!containerRef.value) return;
    view?.destroy();

    const startState = EditorState.create({
        doc: view?.state.doc.toString() || "",
        extensions: [
            basicSetup,
            sqlCompartment.of(sql({
                dialect: getSqlDialect(),
                upperCaseKeywords: true,
                schema: props.currentSchema && props.tables.length
                    ? { [props.currentSchema]: props.tables }
                    : undefined,
            })),
            themeCompartment.of(getTheme()),
            highlightCompartment.of(getHighlight()),
            keymap.of([
                { key: "Tab", run: acceptCompletion },
                {
                    key: "Mod-Enter",
                    run: () => {
                        runQuery();
                        return true;
                    },
                },
            ]),
        ],
    });

    view = new EditorView({
        state: startState,
        parent: containerRef.value,
    });
};

onMounted(createEditor);

onBeforeUnmount(() => {
    view?.destroy();
});

// ---- React to dark mode toggling without recreating the editor ----

watch(
    () => props.isDark,
    () => {
        if (!view) return;
        view.dispatch({
            effects: [
                highlightCompartment.reconfigure(getHighlight()),
                themeCompartment.reconfigure(getTheme()),
            ],
        });
    },
);

// ---- React to dialect changes (MySQL <-> PostgreSQL) ----
watch(() => props.dialect, createEditor);

// ---- React to table list changes (schema autocomplete) ----
const reconfigureSql = () => {
    if (!view) return;
    view.dispatch({
        effects: sqlCompartment.reconfigure(sql({
            dialect: getSqlDialect(),
            upperCaseKeywords: true,
            schema: props.currentSchema && props.tables.length
                ? { [props.currentSchema]: props.tables }
                : undefined,
        })),
    });
};
watch(() => props.tables, reconfigureSql, { deep: true });
watch(() => props.currentSchema, reconfigureSql);

// ---- Actions ----

const runQuery = () => {
    const sqlText = view?.state.doc.toString().trim();
    if (sqlText) {
        emit("execute", sqlText);
    }
};

const clearQuery = () => {
    if (view) {
        view.dispatch({
            changes: { from: 0, to: view.state.doc.length, insert: "" },
        });
    }
    view?.focus();
};

defineExpose({ clearQuery, runQuery });
</script>

<template>
    <div class="border border-border rounded-lg overflow-hidden">
        <div
            class="flex items-center justify-between px-3 py-1.5 bg-muted border-b border-border"
        >
            <span class="text-xs font-medium text-muted-foreground"
                >SQL Query</span
            >
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-muted-foreground hover:text-foreground hover:bg-accent transition-colors"
                    title="Clear"
                    @click="clearQuery"
                >
                    <RotateCcw class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1 h-7 px-2.5 rounded-md text-xs font-medium bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
                    @click="runQuery"
                >
                    <Play class="h-3.5 w-3.5" />
                    Run (Ctrl+Enter)
                </button>
            </div>
        </div>
        <div ref="containerRef" class="min-h-[120px]" />
    </div>
</template>
