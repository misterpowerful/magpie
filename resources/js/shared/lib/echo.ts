import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { env } from '@/config/env'
import api from './api'

declare global {
    interface Window {
        Pusher: typeof Pusher
    }
}

let echoInstance: Echo<'reverb'> | null = null
let attempted = false

/**
 * Live run-progress updates (spec section 9/10, Reverb) are a
 * nice-to-have on top of the dashboard's own fetch-on-mount — if Reverb
 * isn't configured (no VITE_REVERB_* vars), this returns null and
 * callers just don't get live updates rather than crashing the app.
 */
export function getEcho(): Echo<'reverb'> | null {
    if (echoInstance || attempted) {
        return echoInstance
    }

    attempted = true

    if (!env.reverb.appKey || !env.reverb.host) {
        return null
    }

    window.Pusher = Pusher

    const port = Number(env.reverb.port ?? 80)

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: env.reverb.appKey,
        wsHost: env.reverb.host,
        wsPort: port,
        wssPort: port,
        forceTLS: env.reverb.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel) => ({
            authorize(socketId, callback) {
                api
                    .post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
                    .then((response) => callback(null, response.data))
                    .catch((error) => callback(error, null))
            },
        }),
    })

    return echoInstance
}
