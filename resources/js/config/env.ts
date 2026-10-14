function required(name: string, value: string | undefined): string {
    if (!value) {
        throw new Error(`Missing required env var: ${name}`)
    }

    return value
}

export const env = {
    appName: required('VITE_APP_NAME', import.meta.env.VITE_APP_NAME),
}
