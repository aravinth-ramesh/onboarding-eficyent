import { renderHook, act } from '@testing-library/react';
import { usePhoneParts } from './usePhoneParts';

describe('usePhoneParts (report item 4)', () => {
  it('keeps a dial code chosen before any number is typed', () => {
    // Previously the dial was derived from the stored value, which stays blank
    // until a number exists — so the picker snapped back to the default and the
    // country could only be changed after entering a number.
    let stored = '';
    const emit = (next) => { stored = next; };

    const { result } = renderHook(() => usePhoneParts(stored, emit));

    act(() => result.current.setDial('+65'));

    expect(result.current.dial).toBe('+65');
    expect(stored).toBe('');
  });

  it('combines the pending dial code with the number once typed', () => {
    let stored = '';
    const emit = (next) => { stored = next; };

    const { result } = renderHook(() => usePhoneParts(stored, emit));

    act(() => result.current.setDial('+65'));
    act(() => result.current.setNumber('9856545'));

    expect(stored).toBe('+65 9856545');
  });

  it('reads the dial code back out of a stored value', () => {
    const { result } = renderHook(() => usePhoneParts('+44 7700900123', () => {}));

    expect(result.current.dial).toBe('+44');
    expect(result.current.number).toBe('7700900123');
  });
});
