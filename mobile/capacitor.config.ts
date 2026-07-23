import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'id.co.lintaspos.mobile',
  appName: 'LintasPOS',
  webDir: 'dist',
  bundledWebRuntime: false,
  plugins: {
    CapacitorHttp: { enabled: true },
    SplashScreen: { launchShowDuration: 1200, backgroundColor: '#0f766e', showSpinner: false },
    StatusBar: { style: 'DARK', backgroundColor: '#0f766e' },
  },
}

export default config
