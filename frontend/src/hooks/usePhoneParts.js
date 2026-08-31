import { useEffect, useState } from 'react';
import { parsePhone, formatPhone } from '../utils/phoneValue';

/**
 * Drive the two controls behind a phone answer.
 *
 * The dial code is held locally rather than derived from the stored value on
 * every render. A lone dial code is deliberately not an answer -- formatPhone
 * returns blank until a number is typed, so `is_required` still catches an
 * empty field -- but deriving the dial from that blank meant picking a country
 * before typing the number snapped straight back to the default, so the code
 * could only be changed after entering a number (retest item 4).
 *
 * Local state keeps the pending choice visible while the stored value stays
 * empty, and the effect re-syncs when the value changes from elsewhere: a
 * draft loading, a collaborator's edit, or the row being reset.
 */
export function usePhoneParts(value, emit) {
  const parsed = parsePhone(value);
  const [dial, setDial] = useState(parsed.dial);

  useEffect(() => {
    // Only follow the stored value when it actually carries a dial code;
    // otherwise a blank value would overwrite the user's pending choice.
    const next = parsePhone(value);
    if (next.number !== '') setDial(next.dial);
  }, [value]);

  return {
    dial,
    number: parsed.number,
    setDial: (nextDial) => {
      setDial(nextDial);
      emit(formatPhone(nextDial, parsed.number));
    },
    setNumber: (nextNumber) => emit(formatPhone(dial, nextNumber)),
  };
}
