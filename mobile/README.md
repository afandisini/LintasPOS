# LintasPOS Mobile

Scaffold Vue 3 + TypeScript + Vite + Capacitor untuk aplikasi Android LintasPOS.

## Development

```bash
npm install
npm run dev
```

## Android

```bash
npm run build
npx cap add android
npm run cap:sync
npm run cap:android
```

`VITE_API_BASE_URL` diatur melalui `.env.development` atau `.env.production`. Token native memakai Android Keystore melalui secure-storage plugin; fallback `sessionStorage` hanya untuk browser development.
