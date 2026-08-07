import client from './client';

export const getTeam = () => client.get('/onboarding/team');

export const inviteTeamMember = (email) => client.post('/onboarding/team/invite', { email });

export const removeTeamMember = (collaboratorId) => client.delete(`/onboarding/team/${collaboratorId}`);

// Invitation links from email. The lookup is public (the invitee has no
// session yet); accepting requires being signed in as the invited address.
export const getInvitation = (token) => client.get(`/team/invitation/${token}`);

export const acceptInvitation = (token) => client.post(`/onboarding/team/invitation/${token}/accept`);
