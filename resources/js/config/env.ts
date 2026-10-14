function required(name: string, value: string | undefined): string {
    if (!value) {
        throw new Error(`Missing required env var: ${name}`)
    }

    return value
}

export const env = {
    appName: required('VITE_APP_NAME', import.meta.env.VITE_APP_NAME),
    // Optional: live run-progress updates (Reverb) are a nice-to-have,
    // not a hard requirement — the app still works without them (just
    // via the dashboard's own load-on-mount fetch), so these are not
    // required() like appName is.
    reverb: {
        appKey: import.meta.env.VITE_REVERB_APP_KEY || null,
        host: import.meta.env.VITE_REVERB_HOST || null,
        port: import.meta.env.VITE_REVERB_PORT || null,
        scheme: import.meta.env.VITE_REVERB_SCHEME || 'https',
    },
}
