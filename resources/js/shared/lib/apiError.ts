import { isAxiosError } from 'axios'

/**
 * Shape of the backend's unified JSON error envelope (ApiError/
 * ApiErrorRenderer on the Laravel side) — correlationId/code are always
 * present; errors is only present for validation failures.
 */
interface ApiErrorBody {
    correlationId?: string
    code?: string
    message?: string
    errors?: Record<string, string[]>
}

function bodyOf(error: unknown): ApiErrorBody | undefined {
    return isAxiosError(error) ? (error.response?.data as ApiErrorBody | undefined) : undefined
}

/**
 * The message to show for a failed request. Anything other than a
 * validation failure (those point at a field the user can fix) ends with
 * the request's reference id, so what's on screen can be matched to the
 * server-side log line.
 */
export function apiErrorMessage(error: unknown, fallback = 'Something went wrong.'): string {
    const body = bodyOf(error)
    const message = body?.message ?? fallback
    const reference = body?.code === 'validation_failed' ? null : errorReference(error)

    return reference ? `${message} (Reference: ${reference.slice(0, 8)})` : message
}

/**
 * A correlation id to show the user ("Reference: <id>") on any
 * non-validation error, so a support request can be tied back to a
 * server-side log line.
 */
export function errorReference(error: unknown): string | null {
    return bodyOf(error)?.correlationId ?? null
}

export function validationErrors(error: unknown): Record<string, string[]> {
    return bodyOf(error)?.errors ?? {}
}
