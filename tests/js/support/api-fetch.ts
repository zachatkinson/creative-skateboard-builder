/**
 * A double of `@wordpress/api-fetch`: a test says what the next request answers, and reads what was asked.
 */
export const asked: Array< { path: string } > = [];
export const answers: {
	next: ( options: { path: string } ) => Promise< unknown >;
} = { next: () => Promise.resolve( [] ) };

export default function apiFetch< T >( options: {
	path: string;
} ): Promise< T > {
	asked.push( options );
	return answers.next( options ) as Promise< T >;
}
