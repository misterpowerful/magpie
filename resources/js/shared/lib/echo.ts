import type Echo from 'laravel-echo'
import type Pusher from 'pusher-js'
import { env } from '@/config/env'
import api from './api'

declare global {
    interface Window {
        Pusher: typeof Pusher
    }
}

let echoInstance: Echo<'reverb'> | null = null
let loading: Promise<Echo<'reverb'> | null> | null = null

/**
 * Live run-progress updates (spec section 9/10, Reverb) are a
 * nice-to-have on top of the dashboard's own fetch-on-mount — if Reverb
 * isn't configured (no VITE_REVERB_* vars), this resolves to null and
 * callers just don't get live updates rather than crashing the app.
 *
 * laravel-echo and pusher-js are loaded on first use, not with the app:
 * they're a large share of the bundle and unused when Reverb is off.
 */
export function loadEcho(): Promise<Echo<'reverb'> | null> {
    if (loading) {
        return loading
    }

    const { appKey, host } = env.reverb

    if (!appKey || !host) {
        loading = Promise.resolve(null)

        return loading
    }

    loading = Promise.all([import('laravel-echo'), import('pusher-js')]).then(([{ default: EchoClient }, { default: PusherClient }]) => {
        window.Pusher = PusherClient

        const port = Number(env.reverb.port ?? 80)

        echoInstance = new EchoClient({
            broadcaster: 'reverb',
            key: appKey,
            wsHost: host,
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
    })

    return loading
}

/** The client if it has already loaded — for cleanup, which shouldn't trigger a load. */
export function currentEcho(): Echo<'reverb'> | null {
    return echoInstance
}
