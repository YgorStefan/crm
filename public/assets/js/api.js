/**
 * api.js — Camada única de fetch + CSRF do CRM.
 *
 * Centraliza o que antes era repetido em cada arquivo JS:
 *   - leitura do token CSRF da <meta name="csrf-token">
 *   - injeção do token no corpo (_csrf_token) e no header (X-CSRF-Token)
 *   - header X-Requested-With (o backend usa para responder JSON)
 *   - sincronização do token renovado vindo da resposta (csrf_token)
 *
 * Uso:
 *   const { ok, status, data } = await CRM.api.post(url, { campo: valor });
 *   const r = await CRM.api.postForm(url, formData);   // uploads/FormData
 *   const r = await CRM.api.postJson(url, { ... });    // corpo JSON
 */
(function () {
    'use strict';

    window.CRM = window.CRM || {};

    const csrf = {
        get() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },
        sync(token) {
            if (!token) return;
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.content = token;
            // Mantém formulários tradicionais da página com o token válido
            document.querySelectorAll('input[name="_csrf_token"]').forEach(el => { el.value = token; });
        },
    };

    // Executa o fetch, faz parse do JSON (null se não for JSON) e
    // sincroniza o token renovado quando presente na resposta.
    async function send(url, options) {
        const resp = await fetch(url, options);
        let data = null;
        try { data = await resp.json(); } catch (e) { /* resposta sem corpo JSON */ }
        if (data && data.csrf_token) csrf.sync(data.csrf_token);
        return { ok: resp.ok, status: resp.status, data };
    }

    // POST application/x-www-form-urlencoded (objeto ou URLSearchParams)
    function post(url, body = {}) {
        const params = body instanceof URLSearchParams ? body : new URLSearchParams(body);
        params.set('_csrf_token', csrf.get());
        return send(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrf.get(),
            },
            body: params.toString(),
        });
    }

    // POST multipart (FormData) — sem Content-Type manual (o browser define o boundary)
    function postForm(url, formData) {
        formData.set('_csrf_token', csrf.get());
        return send(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrf.get(),
            },
            body: formData,
        });
    }

    // POST application/json (o token vai no header, lido pelo CsrfMiddleware)
    function postJson(url, payload = {}) {
        return send(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrf.get(),
            },
            body: JSON.stringify(payload),
        });
    }

    window.CRM.csrf = csrf;
    window.CRM.api = { post, postForm, postJson };
})();
