// Resolve the effective min/max for a date input by combining the explicit
// `min_date`/`max_date` rules with the `allow_past` / `allow_future` flags.
// Shared by QuestionField (top-level date questions) and TableField (date
// columns) so the two can never drift.
export const todayIso = () => new Date().toISOString().slice(0, 10);

export const dateBound = (rules, edge) => {
  if (!rules) return undefined;
  if (edge === 'min') {
    if (rules.min_date) return rules.min_date;
    if (rules.allow_past === false) return todayIso();
    return undefined;
  }
  if (rules.max_date) return rules.max_date;
  // A minimum age caps the date picker at the latest qualifying birthday, so
  // the native control can't offer an under-age date at all (EOP-32).
  if (rules.min_age != null) {
    const d = new Date();
    d.setFullYear(d.getFullYear() - Number(rules.min_age));
    return d.toISOString().slice(0, 10);
  }
  if (rules.allow_future === false) return todayIso();
  return undefined;
};
