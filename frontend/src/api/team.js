import client from './client';

export const getTeam = () => client.get('/onboarding/team');

export const inviteTeamMember = (email) => client.post('/onboarding/team/invite', { email });

export const removeTeamMember = (collaboratorId) => client.delete(`/onboarding/team/${collaboratorId}`);
