/** GET/PUT /api/preferences — what the scorer and the filters run against. */
export interface Preferences {
    remote_floor: number
    onsite_floor: number
    hard_min: number
    onsite_states: string[]
    include_titles: string[]
    exclude_titles: string[]
    include_stack: string[]
    exclude_stack: string[]
    /** null = using default_scoring_criteria */
    scoring_criteria: string | null
    default_scoring_criteria: string
    resume_text: string | null
    resume_filename: string | null
    resume_uploaded_at: string | null
    has_resume_file: boolean
    /** Pending listings scored against older criteria or an older resume. */
    stale_scored_count: number
}

export type PreferencesUpdate = Partial<
    Pick<
        Preferences,
        | 'remote_floor'
        | 'onsite_floor'
        | 'hard_min'
        | 'onsite_states'
        | 'include_titles'
        | 'exclude_titles'
        | 'include_stack'
        | 'exclude_stack'
        | 'scoring_criteria'
        | 'resume_text'
    >
>
